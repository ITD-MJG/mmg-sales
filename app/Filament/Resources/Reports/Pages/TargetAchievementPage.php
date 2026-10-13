<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\TargetAchievementResource;
use App\Filament\Widgets\Reports\TargetAchievementStatsWidget;
use App\Filament\Widgets\Reports\TargetVsLeadsWidget;
use App\Filament\Widgets\Reports\TargetVsOrdersWidget;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TargetAchievementPage extends Page
{
    use HasFiltersForm;

    protected static string $resource = TargetAchievementResource::class;

    protected string $view = 'filament.resources.reports.pages.target-achievement';

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->endOfYear()->format('Y-m-d'),
            'user_id' => null,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TargetAchievementStatsWidget::class,
            TargetVsOrdersWidget::class,
            TargetVsLeadsWidget::class,
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
                        Select::make('user_id')
                            ->label('Sales Representative')
                            ->options(fn () => User::query()
                                ->whereHas('targets')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getViewData(): array
    {
        return [];
    }
}
