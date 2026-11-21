<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * Rehydrate the lead selector from the saved origin pointer. Without this the
     * picker renders empty on an order that was created from a lead, and saving
     * would look like the lead had been cleared.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (filled($data['lead_id'] ?? null)) {
            $data['order_source'] = 'leads';
            $data['source_record_id'] = (string) $data['lead_id'];
        }

        return $data;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Order updated successfully');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
