<?php

namespace App\Models;

use App\Traits\HasCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Customer extends Model
{
    use HasCode, HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'customer_name',
        'type',
        'other_type',
        'tax_number',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'email',
        'phone',
        'website',
        'is_active',
        'status',
        'cd_ncd_type',
        'segment_id',
        'sub_segment_id',
        'customer_group_id',
        'customer_code',
        'internal_code',
        'max_contact_persons',
        'payment_terms',
    ];

    protected $codeColumn = 'customer_code';

    protected $codePrefix = 'CST';

    public function getDisplayNameAttribute(): string
    {
        return $this->customer_name ?? $this->name;
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'customer_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'customer_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'end_customer_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'customer_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function subSegment(): BelongsTo
    {
        return $this->belongsTo(SubSegment::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updated(function (Customer $customer) {
            if ($customer->isDirty('status') && $customer->status === 'inactive') {
                $customer->contacts()->where('status', 'active')->update(['status' => 'inactive']);
            }
        });
    }
}
