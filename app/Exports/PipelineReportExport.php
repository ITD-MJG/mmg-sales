<?php

namespace App\Exports;

use App\DTOs\ReportFilterData;
use App\Services\Reports\PipelineReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class PipelineReportExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    private PipelineReportService $service;

    public function __construct(
        private ReportFilterData $filters
    ) {
        $this->service = app(PipelineReportService::class);
    }

    public function collection()
    {
        return $this->service->getExportData($this->filters);
    }

    public function headings(): array
    {
        return [
            'Code',
            'Title',
            'Customer',
            'Sales Rep',
            'Status',
            'Confidence',
            'Estimated Revenue',
            'Expected Closing',
            'Created Date',
            'Converted Date',
            'Disqualified Date',
        ];
    }

    public function map($row): array
    {
        return [
            $row['Code'],
            $row['Title'],
            $row['Customer'],
            $row['Sales Rep'],
            $row['Status'],
            $row['Confidence'],
            $row['Estimated Revenue'],
            $row['Expected Closing'],
            $row['Created Date'],
            $row['Converted Date'],
            $row['Disqualified Date'],
        ];
    }

    public function title(): string
    {
        return 'Lead Report';
    }
}
