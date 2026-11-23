<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'amount',
        'effective_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_date' => 'date',
    ];

    /**
     * The standing a payment leaves the order in, given everything received.
     *
     * Status is derived, never chosen: a hand-picked `full` on a half-paid order
     * is exactly the drift this replaces.
     */
    public static function deriveStatus(float $paid, float $orderTotal): string
    {
        if ($paid <= 0) {
            return 'pending';
        }

        return $paid >= $orderTotal ? 'full' : 'partial';
    }

    /**
     * Re-derive every status on an order from its amounts, oldest payment first.
     *
     * Needed after a payment is edited or deleted: each row's status is a
     * snapshot of the running total at that point, so changing one amount
     * invalidates every row after it.
     */
    public static function resyncStatuses(Order $order): void
    {
        $orderTotal = (float) $order->total_amount;
        $running = 0.0;

        foreach ($order->paymentStatuses()->orderBy('id')->get() as $payment) {
            $running += (float) $payment->amount;

            $status = self::deriveStatus($running, $orderTotal);

            if ($payment->status !== $status) {
                $payment->update(['status' => $status]);
            }
        }
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
