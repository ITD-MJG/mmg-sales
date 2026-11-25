<?php

namespace App\Filament\Resources\Reports\Pages;

use App\DTOs\ReportFilterData;
use App\Exports\ProductReportExport;
use App\Filament\Resources\Reports\ProductReportResource;
use App\Filament\Widgets\Reports\MonthlyRevenueTrendWidget;
use App\Filament\Widgets\Reports\ProductReportStatsWidget;
use App\Filament\Widgets\Reports\ProductRevenueByPrincipalWidget;
use App\Filament\Widgets\Reports\ProductRevenueBySegmentWidget;
use App\Filament\Widgets\Reports\TopSellingProductsWidget;
use App\Models\Customer;
use App\Models\Principal;
use App\Models\Segment;
use App\Models\Territory;
use App\Models\User;
use App\Services\PrincipalScopeService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;

class ProductReportPage extends Page
{
    use HasFiltersForm;

    protected static string $resource = ProductReportResource::class;

    protected string $view = 'filament.resources.reports.pages.product-report';

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
            ProductReportStatsWidget::class,
            MonthlyRevenueTrendWidget::class,
            ProductRevenueByPrincipalWidget::class,
            ProductRevenueBySegmentWidget::class,
            TopSellingProductsWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): array
    {
        return [
            'default' => 1,
            'md' => 2,
            'lg' => 3,
        ];
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
                                Select::make('principal_id')
                                    ->label('Principal')
                                    ->options(fn () => PrincipalScopeService::principals(Principal::query(), auth()->user())->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('segment_id')
                                    ->label('Segment')
                                    ->options(Segment::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('territory_id')
                                    ->label('Territory')
                                    ->options(Territory::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('user_id')
                                    ->label('Sales Representative')
                                    ->options(User::pluck('name', 'id'))
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
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->action(function () {
                    $filterData = ReportFilterData::fromArray($this->filters);

                    return Excel::download(
                        new ProductReportExport($filterData),
                        'product_report_'.now()->format('Y-m-d').'.xlsx'
                    );
                }),
            Action::make('reset_filters')
                ->label('Reset')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->mount()),
        ];
    }

    protected function getViewData(): array
    {
        return [];
    }
}
