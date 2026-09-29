<?php

namespace App\Filament\Resources\Opportunities\Pages;

use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Models\Activity;
use App\Models\Customer;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOpportunity extends EditRecord
{
    protected static string $resource = OpportunityResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Opportunity updated successfully');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['assigned_users'])) {
            $assignedUsers = $data['assigned_users'];
            unset($data['assigned_users']);

            $record = $this->getRecord();
            if ($record) {
                $record->collaborators()
                    ->syncWithPivotValues($assignedUsers, ['added_by' => auth()->id()]);
            }
        }

        if (isset($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
            if ($customer) {
                $data['customer_name'] = $customer->name;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $opportunity = $this->getRecord();
        if ($opportunity->customer_id) {
            Activity::where('customer_id', $opportunity->customer_id)
                ->whereNull('opportunity_id')
                ->update(['opportunity_id' => $opportunity->id]);
        }
    }
}
