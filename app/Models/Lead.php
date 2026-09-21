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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    protected $fillable = [
        'title',
        'customer_name',
        'email',
        'phone',
        'status',
        'source',
        'priority',
        'estimated_value',
        'estimated_revenue',
        'estimated_completion_date',
        'notes',
        'customer_id',
        'converted_at',
        'last_contacted_at',
        'assigned_to',
        'position',
        'lead_code',
        'created_by',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'estimated_revenue' => 'decimal:2',
        'estimated_completion_date' => 'date',
        'converted_at' => 'datetime',
        'last_contacted_at' => 'datetime',
    ];

    protected $codeColumn = 'lead_code';

    // Uses generateForLead from ResourceCodeGenerator (LEAD-YYYYMM-XXXX)
    public function generateCode(): string
    {
        $generator = app(ResourceCodeGenerator::class);

        return $generator->generateForLead();
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'lead_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'lead_id')->orderBy('performed_at', 'desc');
    }

    public function activityComments(): HasManyThrough
    {
        return $this->hasManyThrough(ActivityComment::class, Activity::class);
    }

    /**
     * Whether the user owns the lead: creator or listed collaborator.
     * Mirrors the lead-visibility rule used by ActivitiesTable and ActivityScopeService.
     */
    public function isAccessibleBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        return $this->collaborators()->whereKey($user->getKey())->exists();
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('created_by', $user->id)
                ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->whereKey($user->getKey()));
        });
    }

    public function latestActivity(): HasOne
    {
        return $this->hasOne(Activity::class, 'lead_id')->latestOfMany('performed_at');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'lead_product')->withTimestamps();
    }
}
