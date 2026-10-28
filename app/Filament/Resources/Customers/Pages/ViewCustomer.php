<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Widgets\CustomerActivityStatsWidget;
use App\Filament\Widgets\CustomerCommentHistoryWidget;
use App\Filament\Widgets\CustomerRecentActivitiesWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CustomerActivityStatsWidget::class,
            CustomerRecentActivitiesWidget::class,
            CustomerCommentHistoryWidget::class,
        ];
    }
}
