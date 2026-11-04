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
    ];

    /**
     * Numeric weight per confidence band.
     *
     * Confidence is a judgement, not a forecast input: pipeline value is the
     * raw estimated revenue, and actual revenue only arrives when the lead
     * converts and an order is raised. The weights exist solely so the band
     * can be stored as an integer on activities (which cannot hold the band
     * string) and ordered meaningfully in queries.
     *
     * @var array<string, int>
     */
    public const CONFIDENCE_WEIGHTS = [
        'low' => 10,
        'medium' => 30,
        'high' => 60,
        'very_high' => 80,
        'committed' => 95,
    ];

    /**
     * The lead enum stops at raw intake. Pipeline stages (won/lost, proposal…)
     * live on opportunities, so they must never appear here.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'converted' => 'Converted',
        'disqualified' => 'Disqualified',
    ];

    public const CONFIDENCE_LABELS = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'very_high' => 'Very High',
        'committed' => 'Committed',
    ];

    /**
     * Weight => label, for the activity form's confidence select: activities
     * keep the legacy 0-100 integer, so they store the band's weight.
     *
     * @return array<int, string>
     */
    public static function confidenceWeightOptions(): array
    {
        return array_combine(
            array_values(self::CONFIDENCE_WEIGHTS),
            array_values(self::CONFIDENCE_LABELS),
        );
    }

    public static function confidenceBandForWeight(?int $weight): ?string
    {
        if (blank($weight)) {
            return null;
        }

        $band = array_search((int) $weight, self::CONFIDENCE_WEIGHTS, true);

        return $band === false ? null : $band;
    }

    public function getConfidenceWeightAttribute(): ?int
    {
        return self::CONFIDENCE_WEIGHTS[$this->confidence_level] ?? null;
    }

    public function isConfidenceAssessed(): bool
    {
        return filled($this->confidence_level);
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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'lead_product')->withTimestamps();
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

    public function activityComments(): HasManyThrough
    {
        return $this->hasManyThrough(ActivityComment::class, Activity::class, 'lead_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'converted_from_lead_id');
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
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
