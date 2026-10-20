<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\TargetReportResource;
use App\Filament\Widgets\Reports\TargetReportStatsWidget;
use App\Filament\Widgets\Reports\TargetVsLeadWidget;
use App\Filament\Widgets\Reports\TargetVsOrderWidget;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Distributor;
use App\Models\Principal;
use App\Models\Territory;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TargetReportPage extends Page
{
    use HasFiltersForm;

    protected static string $resource = TargetReportResource::class;

    protected string $view = 'filament.resources.reports.pages.target-report';

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->endOfYear()->format('Y-m-d'),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TargetReportStatsWidget::class,
            TargetVsLeadWidget::class,
            TargetVsOrderWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Date Range')
                    ->columnSpan(1)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->default(now()->startOfYear())
                                    ->required(),
                                DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->default(now()->endOfYear())
                                    ->required(),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Filters')
                    ->columnSpan(3)
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Sales Representative')
                                    ->options(User::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('territory_id')
                                    ->label('Territory')
                                    ->options(Territory::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('department_id')
                                    ->label('Department')
                                    ->options(Department::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('principal_id')
                                    ->label('Principal')
                                    ->options(Principal::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('distributor_id')
                                    ->label('Distributor')
                                    ->options(Distributor::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->options(Customer::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_filters')
                ->label('Reset')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->mount()),
        ];
    }
}
