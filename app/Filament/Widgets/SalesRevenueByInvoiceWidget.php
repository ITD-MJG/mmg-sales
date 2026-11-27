<?php

namespace App\Filament\Widgets;

use App\Services\Reports\SalesRevenueByInvoiceService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class SalesRevenueByInvoiceWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.sales-revenue-by-invoice';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 35;

    public string $dimension = 'territory';

    public string $metric = 'total';

    public ?string $selectedKey = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_sales_dashboard') ?? false;
    }

    public function updatedDimension(): void
    {
        $this->selectedKey = null;
    }

    public function updatedMetric(): void
    {
        $this->selectedKey = null;
    }

    public function selectGroup(string $key): void
    {
        $this->selectedKey = $this->selectedKey === $key ? null : $key;
    }

    /**
     * @return list<array{key: string, label: string, value: float}>
     */
    public function getBreakdown(): array
    {
        return array_slice($this->service()->breakdown($this->dimension, $this->metric), 0, 10);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, count: int, total: float}
     */
    public function getInvoices(): array
    {
        return $this->service()->invoices($this->dimension, $this->metric, $this->selectedKey);
    }

    public function getDashboardLabel(): string
    {
        if ($this->selectedKey === null) {
            return 'All invoices';
        }

        foreach ($this->getBreakdown() as $group) {
            if ($group['key'] === $this->selectedKey) {
                return $group['label'];
            }
        }

        return 'All invoices';
    }

    /**
     * @return array<string, string>
     */
    public function getDimensionOptions(): array
    {
        return [
            'territory' => 'Territory',
            'sales_rep' => 'Sales Rep',
            'sales_supervisor' => 'Sales Supervisor',
            'principal' => 'Principal',
            'marketing' => 'Marketing',
            'customer' => 'Customer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getMetricOptions(): array
    {
        return [
            'total' => 'Total Amount',
            'paid' => 'Paid Amount',
        ];
    }

    private function service(): SalesRevenueByInvoiceService
    {
        $filters = $this->pageFilters ?? [];

        return new SalesRevenueByInvoiceService(
            Carbon::parse($filters['start_date'] ?? now()->startOfYear()),
            Carbon::parse($filters['end_date'] ?? now()),
            auth()->user(),
        );
    }
}
