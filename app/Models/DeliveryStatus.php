<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'carrier',
        'tracking_number',
        'shipped_date',
        'delivered_date',
        'proof_of_delivery',
        'notes',
    ];

    protected $casts = [
        'shipped_date' => 'date',
        'delivered_date' => 'date',
    ];

    /**
     * The stage this row represents. Derived from its own fields, never stored,
     * so a row cannot disagree with what it records.
     */
    public function getStatusAttribute(): string
    {
        return match (true) {
            (bool) $this->delivered_date => 'delivered',
            (bool) $this->shipped_date => 'shipped',
            (bool) $this->carrier => 'in_transit',
            default => 'pending',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'delivered' => 'Delivered',
            'shipped' => 'Shipped',
            'in_transit' => 'In Transit',
            default => 'Pending',
        };
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
