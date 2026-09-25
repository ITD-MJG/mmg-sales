<?php

namespace App\Models;

use App\Services\ResourceCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Order extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'tahun',
        'bulan',
        'department_id',
        'head_position_id',
        'pm_jpm_pe_position_id',
        'rsm_asm_position_id',
        'spv_position_id',
        'sales',
        'end_customer_id',
        'principal_id',
        'reg_inst',
        'sales_type_id',
        'discount_on',
        'net_sales_total',
        'jual_kso',
        'distributor_id',
        'order_number',
        'lead_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'shipping_address',
        'billing_address',
        'notes',
        'payment_method',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reg_inst' => 'array',
            'sales' => 'array',
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            // ponytail: derive tahun/bulan from order_date, hidden TextInput defaults unreliable via Livewire
            if (empty($model->tahun)) {
                $model->tahun = $model->order_date ? (int) $model->order_date->format('Y') : now()->year;
            }
            if (empty($model->bulan)) {
                $model->bulan = $model->order_date ? (int) $model->order_date->format('m') : now()->month;
            }

            if (empty($model->order_number)) {
                $generator = app(ResourceCodeGenerator::class);
                $model->order_number = $generator->generateForOrder($model->tahun);
            }

            if (is_null($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function headPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'head_position_id');
    }

    public function pmJpmPePosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'pm_jpm_pe_position_id');
    }

    public function rsmAsmPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'rsm_asm_position_id');
    }

    public function spvPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'spv_position_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'end_customer_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryStatuses(): HasMany
    {
        return $this->hasMany(DeliveryStatus::class);
    }

    public function paymentStatuses(): HasMany
    {
        return $this->hasMany(PaymentStatus::class);
    }

    /**
     * Sum of line items. Kept separate from the `net_sales_total` column,
     * which is the stored value and is the only source for imported orders
     * (the import writes a header with no order_items).
     */
    public function orderItemsNetTotal(): float
    {
        return (float) $this->orderItems()->sum('subtotal');
    }

    public function getLatestDeliveryStatusAttribute(): ?DeliveryStatus
    {
        return $this->deliveryStatuses()->latest()->first();
    }

    public function getLatestPaymentStatusAttribute(): ?PaymentStatus
    {
        return $this->paymentStatuses()->latest()->first();
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'order_number', 'order_date', 'total_amount', 'net_sales_total',
                'expected_delivery_date', 'actual_delivery_date',
                'shipping_address', 'billing_address', 'payment_method', 'notes',
            ])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}
