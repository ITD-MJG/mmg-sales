<?php

namespace App\Models;

use App\Services\ResourceCodeGenerator;
use App\Traits\HasCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Lead extends Model
{
    use HasCode, HasFactory, LogsActivity, SoftDeletes;

    protected $codeColumn = 'lead_code';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    protected $fillable = [
        'title',
        'customer_name',
        'customer_id',
        'contact_person',
        'email',
        'phone',
        'status',
        'source',
        'priority',
        'confidence_level',
        'notes',
        'estimated_revenue',
        'expected_closing_date',
        'created_by',
        'converted_at',
        'disqualified_at',
        'last_contacted_at',
        'lead_code',
        'position',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
        'disqualified_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'estimated_revenue' => 'decimal:2',
        'expected_closing_date' => 'date',
        'confidence_level' => 'integer',
    ];

    /**
     * The EspoCRM pipeline vocabulary, minus `Assigned`: assignment is the
     * collaborators relation here, not a status, so a lead can be assigned and
     * sit at Proposal at the same time.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'new' => 'New Lead',
        'prospect' => 'Prospect/ Discovery',
        'qualification' => 'Qualification/ Acceptance of Need',
        'quotation' => 'Quotation',
        'convince' => 'Convince',
        'negotiate' => 'Negotiate',
        'decision_maker' => 'Decision Maker',
        'closed_won' => 'Win',
        'closed_lost' => 'Lost',
        'hold' => 'Hold',
    ];

    /**
     * The confidence a status implies when a rep has not judged the lead
     * themselves. Used as the default when a status is chosen, and applied on
     * save only when confidence was not set in the same change.
     *
     * @var array<string, int>
     */
    public const STATUS_CONFIDENCE = [
        'new' => 10,
        'prospect' => 20,
        'qualification' => 30,
        'quotation' => 40,
        'convince' => 50,
        'negotiate' => 60,
        'decision_maker' => 70,
        'closed_won' => 100,
        'closed_lost' => 0,
        'hold' => 30,
    ];

    public static function defaultConfidenceForStatus(?string $status): ?int
    {
        return self::STATUS_CONFIDENCE[$status] ?? null;
    }

    /**
     * Statuses that mean the lead is no longer being worked. Kept here so the
     * "open" definition has one home instead of being re-typed per widget.
     *
     * @var list<string>
     */
    public const CLOSED_STATUSES = ['closed_won', 'closed_lost'];

    /**
     * The one judgement a lead records as a hard stop. Every other status is
     * either intake or progress.
     */
    public const LOST_STATUS = 'closed_lost';

    /**
     * Confidence is a 0-100 percentage: a rep's own judgement of how likely the
     * lead is to close. Nullable on purpose so an unassessed lead stays
     * distinguishable from one deliberately rated at a low percentage.
     *
     * These ranges drive the list/report filters and the confidence chart.
     *
     * @var array<string, array{min: int, max: int, label: string}>
     */
    public const CONFIDENCE_RANGES = [
        '0-25' => ['min' => 0, 'max' => 25, 'label' => 'Low (0-25%)'],
        '26-50' => ['min' => 26, 'max' => 50, 'label' => 'Medium (26-50%)'],
        '51-75' => ['min' => 51, 'max' => 75, 'label' => 'High (51-75%)'],
        '76-100' => ['min' => 76, 'max' => 100, 'label' => 'Very High (76-100%)'],
    ];

    /**
     * @return array<string, string>
     */
    public static function confidenceFilterOptions(): array
    {
        return collect(self::CONFIDENCE_RANGES)
            ->map(fn (array $range): string => $range['label'])
            ->put('none', 'Not assessed')
            ->all();
    }

    /**
     * @return array{0: int, 1: int}|null [min, max] for a range key, null for "none" or unknown.
     */
    public static function confidenceRange(?string $key): ?array
    {
        if (blank($key) || ! isset(self::CONFIDENCE_RANGES[$key])) {
            return null;
        }

        return [self::CONFIDENCE_RANGES[$key]['min'], self::CONFIDENCE_RANGES[$key]['max']];
    }

    public static function confidenceLabel(int|string|null $percent): string
    {
        if ($percent === null || $percent === '') {
            return 'Not assessed';
        }

        return (int) $percent.'%';
    }

    public function isConfidenceAssessed(): bool
    {
        return $this->confidence_level !== null;
    }

    public function generateCode(): string
    {
        return app(ResourceCodeGenerator::class)->generateForLead();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Lead $lead) {
            if (is_null($lead->created_by) && auth()->check()) {
                $lead->created_by = auth()->id();
            }
        });

        static::saving(function (Lead $lead) {
            if (! $lead->isDirty('status')) {
                return;
            }

            // A confidence set in the same save is a deliberate judgement.
            if ($lead->isDirty('confidence_level') && $lead->confidence_level !== null) {
                return;
            }

            // Only replace a confidence that was itself derived from the old
            // status; a hand-set value survives a stage move.
            $originalConfidence = $lead->getOriginal('confidence_level');
            $wasDerived = $originalConfidence === null
                || $originalConfidence === self::defaultConfidenceForStatus($lead->getOriginal('status'));

            if ($wasDerived) {
                $lead->confidence_level = self::defaultConfidenceForStatus($lead->status);
            }
        });
    }

    public function getAgingAttribute(): string
    {
        $end = $this->converted_at ?? now();
        $days = round($this->created_at->diffInHours($end) / 24, 1);

        return $days.' '.str('day')->plural($days);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contactPerson(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_person');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lead_collaborators')
            ->withPivot('added_by')
            ->withTimestamps();
    }

    /**
     * The pivot carries quantity, unit_price and a denormalised principal_id
     * for imported rows. They must be selected explicitly or the order form
     * silently prefills quantity 1 and the product's list price.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'lead_product')
            ->withPivot(['principal_id', 'quantity', 'unit_price'])
            ->withTimestamps();
    }

    public function leadProductItems(): HasMany
    {
        return $this->hasMany(LeadProduct::class, 'lead_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'lead_id')->orderBy('performed_at', 'desc');
    }

    public function latestActivity(): HasOne
    {
        return $this->hasOne(Activity::class, 'lead_id')->latestOfMany('performed_at');
    }

    /**
     * The opportunity this lead was converted into, if the opportunity pipeline
     * is in use. Null while the lead is still raw intake.
     */
    public function opportunity(): HasOne
    {
        return $this->hasOne(Opportunity::class, 'converted_from_lead_id');
    }

    public function activityComments(): HasManyThrough
    {
        return $this->hasManyThrough(ActivityComment::class, Activity::class, 'lead_id');
    }

    /**
     * Leads that list at least one product supplied by the given principal.
     *
     * The pivot carries a denormalised principal_id for imported rows, so both
     * it and the product's own principal are checked.
     */
    public function scopeForPrincipal(Builder $query, int $principalId): Builder
    {
        return $query->whereHas('leadProductItems', function (Builder $item) use ($principalId): void {
            $item->where('principal_id', $principalId)
                ->orWhereHas('product', fn (Builder $product): Builder => $product->where('principal_id', $principalId));
        });
    }
}
