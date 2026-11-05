<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\SalesStatisticsResource;
use App\Filament\Widgets\Reports\SalesStatisticsStatsWidget;
use App\Filament\Widgets\Reports\SalesStatisticsTeamWidget;
use App\Filament\Widgets\Reports\SalesStatisticsTrendWidget;
use App\Models\Department;
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
use Illuminate\Database\Eloquent\Builder;

class SalesStatisticsPage extends Page
{
    use HasFiltersForm;

    protected static string $resource = SalesStatisticsResource::class;

    protected string $view = 'filament.resources.reports.pages.sales-statistics';

    public function mount(): void
    {
        $this->filters = [
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->endOfYear()->format('Y-m-d'),
            'user_id' => null,
            'manager_id' => null,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            SalesStatisticsStatsWidget::class,
            SalesStatisticsTrendWidget::class,
            SalesStatisticsTeamWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
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
                                    ->required()
                                    ->live(),
                                DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->default(now()->endOfYear())
                                    ->required()
                                    ->live(),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Filters')
                    ->columnSpan(3)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Sales Rep')
                                    ->options(fn (): array => self::salesDepartmentOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                Select::make('manager_id')
                                    ->label('Manager')
                                    ->options(fn (): array => self::salesDepartmentOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live(),
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

    /**
     * Only the Sales department is reportable here. Department names are not
     * unique in this database (two "Sales" rows exist), so matching on the name
     * is the stable key — `Department::SALES` — and never on the generated code.
     *
     * @return array<int, string>
     */
    public static function salesDepartmentOptions(): array
    {
        return User::query()
            ->whereHas('department', fn (Builder $query) => $query->where('name', Department::SALES))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function getViewData(): array
    {
        return [];
    }
}
