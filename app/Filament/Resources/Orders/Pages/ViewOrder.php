<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Actions\UpdatePaymentAction;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            // Same action as the one on the orders table, so the header offers
            // the identical payment form and history. On a record page the
            // action resolves `$record` from the page itself.
            UpdatePaymentAction::make(),
        ];
    }
}
