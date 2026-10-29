<?php

namespace App\Filament\Resources\Principals\Pages;

use App\Filament\Resources\Principals\PrincipalResource;
use App\Filament\Widgets\PrincipalLeadsWidget;
use App\Filament\Widgets\PrincipalOrdersWidget;
use App\Filament\Widgets\PrincipalStatsWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPrincipal extends ViewRecord
{
    protected static string $resource = PrincipalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PrincipalStatsWidget::class,
            PrincipalLeadsWidget::class,
            PrincipalOrdersWidget::class,
        ];
    }
}
