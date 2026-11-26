<?php

namespace App\Models;

use App\Observers\OrderObserver;
use App\Services\ResourceCodeGenerator;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'tahun',
        'bulan',
        'sales_id',
        'end_customer_id',
        'lead_id',
        'principal_id',
        'reg_inst',
        'sales_type_id',
        'discount_on',
        'net_sales_total',
        'jual_kso',
        'distributor_id',
        'order_number',
        'order_source',
        'opportunity_id',
        'po_number',
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
            'sales_id' => 'integer',
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Order $order) {
            if (filled($order->lead_id) && filled($order->opportunity_id)) {
                throw new InvalidArgumentException(
                    'Order cannot belong to both a lead and an opportunity.'
                );
            }
        });

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

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
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

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    /**
     * The intake lead this order originated from. Never cleared when the lead is
     * converted — the resulting opportunity is reachable via `lead->opportunity`.
     */
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
     * Everything received against this order.
     *
     * Each `payment_statuses` row is one payment, so the order's standing is
     * their sum, not the newest row's amount.
     */
    public function paidAmount(): float
    {
        $payments = $this->relationLoaded('paymentStatuses')
            ? $this->paymentStatuses
            : $this->paymentStatuses()->get();

        return (float) $payments->whereIn('status', ['partial', 'full'])->sum('amount');
    }

    /**
     * What is still owed. Clamped at zero because an overpayment is rejected
     * upstream; a negative here could only ever mean corrupt data.
     */
    public function remainingBalance(): float
    {
        return max(0, (float) $this->total_amount - $this->paidAmount());
    }

    /**
     * Orders that contain at least one line item of one of the given principals.
     *
     * Imported orders have no line items, so the header principal is matched too.
     * An empty list matches nothing rather than everything.
     *
     * @param  array<int, int>  $principalIds
     */
    public function scopeForPrincipals(Builder $query, array $principalIds): Builder
    {
        if ($principalIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $order) use ($principalIds): void {
            $order->whereIn('orders.principal_id', $principalIds)
                ->orWhereHas('orderItems', function (Builder $item) use ($principalIds): void {
                    $item->whereIn('order_items.principal_id', $principalIds)
                        ->orWhereHas('product', fn (Builder $product): Builder => $product->whereIn('products.principal_id', $principalIds));
                });
        });
    }

    /**
     * Whether this order contains a product of any of the given principals.
     *
     * @param  array<int, int>  $principalIds
     */
    public function hasAnyAssignedPrincipal(array $principalIds): bool
    {
        return self::query()->whereKey($this->getKey())->forPrincipals($principalIds)->exists();
    }

    public function scopeForPrincipal(Builder $query, int $principalId): Builder
    {
        return $query->forPrincipals([$principalId]);
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
        // Ordered by id rather than created_at: the timestamp has second
        // precision, so two rows recorded in the same second would otherwise
        // surface in whichever order the engine felt like.
        return $this->deliveryStatuses()->latest('id')->first();
    }

    public function getLatestPaymentStatusAttribute(): ?PaymentStatus
    {
        // Ordered by id rather than created_at: the timestamp has second
        // precision, so two payments recorded in the same second would
        // otherwise surface in whichever order the engine felt like.
        return $this->paymentStatuses()->latest('id')->first();
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
