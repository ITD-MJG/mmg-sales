<?php

namespace App\Models;

use App\Services\ResourceCodeGenerator;
use App\Traits\HasCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'notes',
        'assigned_to',
        'created_by',
        'converted_at',
        'disqualified_at',
        'last_contacted_at',
        'lead_code',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
        'disqualified_at' => 'datetime',
        'last_contacted_at' => 'datetime',
    ];

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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'lead_id')->orderBy('performed_at', 'desc');
    }

    public function latestActivity(): HasOne
    {
        return $this->hasOne(Activity::class, 'lead_id')->latestOfMany('performed_at');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'converted_from_lead_id');
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }
}
