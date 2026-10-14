<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Widgets\SalesOrdersWidget;
use App\Filament\Widgets\SalesTargetWidget;
use App\Jobs\ImportOrdersJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            SalesTargetWidget::class,
            SalesOrdersWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import Orders')
                ->color('success')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn () => auth()->user()->hasAnyRole(['Super Admin', 'Regional Sales Manager', 'Area Sales Manager']))
                ->modalHeading('Import Orders via Excel')
                ->modalDescription('Upload an Excel (.xlsx) file matching the fixed template to import orders in bulk.')
                ->form([
                    FileUpload::make('attachment')
                        ->label('Excel File')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('public')
                        ->directory('imports')
                        ->visibility('public')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $path = storage_path('app/public/'.$data['attachment']);

                    ImportOrdersJob::dispatch($path, auth()->id());

                    Notification::make()
                        ->title('Import started')
                        ->body('The orders are being imported in the background. You will be notified when finished.')
                        ->success()
                        ->send();
                })
                ->extraModalActions([
                    Action::make('downloadTemplate')
                        ->label('Download Template')
                        ->url(asset('assets/template/DATA SALES_MMG.xlsx'))
                        ->openUrlInNewTab()
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            CreateAction::make(),
        ];
    }
}
