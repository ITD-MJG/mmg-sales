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

class Activity extends Model
{
    use HasCode, HasFactory;

    protected $fillable = [
        'lead_id',
        'user_id',
        'customer_id',
        'contact_id',
        'type',
        'subject',
        'description',
        'performed_at',
        'visit_started_at',
        'visit_ended_at',
        'location',
        'purpose',
        'expectations',
        'targets',
        'stakeholder_feedback',
        'is_worth_keeping',
        'confidence_level',
        'next_contact_date',
        'follow_up_notes',
        'meeting_link',
        'messaging_platform',
        'duration_minutes',
        'outcome',
        'activity_code',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'visit_started_at' => 'datetime',
        'visit_ended_at' => 'datetime',
        'is_worth_keeping' => 'boolean',
        'confidence_level' => 'integer',
        'next_contact_date' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // TODO: Re-enable after review
        // static::creating(function ($activity) {
        //     $minDate = now()->subDays(3)->startOfDay();
        //
        //     if ($activity->performed_at && $activity->performed_at->lt($minDate)) {
        //         throw new \InvalidArgumentException('Activity date cannot be more than 3 days in the past.');
        //     }
        // });
    }

    protected $codeColumn = 'activity_code';

    protected $codePrefix = 'ACT';

    public function generateCode(): string
    {
        return app(ResourceCodeGenerator::class)->generateForActivity();
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ActivityComment::class)->latest();
    }

    /**
     * Whether the user is attached to this activity: the rep who logged it,
     * a listed attendee, or the creator/collaborator of the parent lead.
     */
    public function isAccessibleBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->attendees()->whereKey($user->getKey())->exists()) {
            return true;
        }

        return $this->lead?->isAccessibleBy($user) ?? false;
    }

    /**
     * Scope to activities the user is attached to (own, attendee, or lead-linked).
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereHas('attendees', fn (Builder $attendees) => $attendees->whereKey($user->getKey()))
                ->orWhereHas('lead', fn (Builder $lead) => $lead->accessibleBy($user));
        });
    }
}
