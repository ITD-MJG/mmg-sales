<?php

namespace App\Filament\Resources\Reports\Pages;

use App\DTOs\ReportFilterData;
use App\Exports\PipelineReportExport;
use App\Filament\Resources\Reports\PipelineReportResource;
use App\Filament\Widgets\Reports\MonthlyPipelineTrendWidget;
use App\Filament\Widgets\Reports\PipelineBySalesRepresentativeWidget;
use App\Filament\Widgets\Reports\PipelineByStatusWidget;
use App\Filament\Widgets\Reports\PipelineReportStatsWidget;
use App\Models\Customer;
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
use Maatwebsite\Excel\Facades\Excel;

class PipelineReportPage extends Page
{
    use HasFiltersForm;

    protected static string $resource = PipelineReportResource::class;

    protected string $view = 'filament.resources.reports.pages.pipeline-report';

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
            PipelineReportStatsWidget::class,
            PipelineByStatusWidget::class,
            MonthlyPipelineTrendWidget::class,
            PipelineBySalesRepresentativeWidget::class,
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
                                Select::make('user_id')
                                    ->label('Sales Representative')
                                    ->options(fn () => User::whereIn('id', fn ($q) => $q->from('opportunity_collaborators')->select('user_id'))->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->options(Customer::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                Select::make('lead_status')
                                    ->label('Lead Status')
                                    ->options([
                                        'new' => 'New',
                                        'contacted' => 'Contacted',
                                        'qualified' => 'Qualified',
                                        'proposal' => 'Proposal',
                                        'negotiation' => 'Negotiation',
                                        'won' => 'Won',
                                        'lost' => 'Lost',
                                    ]),
                                Select::make('lead_source')
                                    ->label('Source')
                                    ->options([
                                        'website' => 'Website',
                                        'referral' => 'Referral',
                                        'cold_call' => 'Cold Call',
                                        'trade_show' => 'Trade Show',
                                        'partner' => 'Partner',
                                        'other' => 'Other',
                                    ]),
                                Select::make('lead_priority')
                                    ->label('Priority')
                                    ->options([
                                        'low' => 'Low',
                                        'medium' => 'Medium',
                                        'high' => 'High',
                                        'urgent' => 'Urgent',
                                    ]),
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
                        new PipelineReportExport($filterData),
                        'pipeline_report_'.now()->format('Y-m-d').'.xlsx'
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
