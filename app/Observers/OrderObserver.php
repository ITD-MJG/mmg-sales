<?php

namespace App\Observers;

use App\Models\Order;

class OrderObserver
{
    public function created(Order $order): void
    {
        if (! $order->opportunity_id) {
            return;
        }

        $opportunity = $order->opportunity;

        if (! $opportunity) {
            return;
        }

        $opportunity->stage = 'won';
        $opportunity->converted_at = now();
        $opportunity->save();
    }

    public function updated(Order $order): void
    {
        //
    }

    public function deleted(Order $order): void
    {
        //
    }

    public function restored(Order $order): void
    {
        //
    }

    public function forceDeleted(Order $order): void
    {
        //
    }
}
