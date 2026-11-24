<?php

namespace App\Filament\Widgets\Concerns;

use NumberFormatter;

/**
 * Presentation helpers the KPI rows share, so the same figure is coloured and
 * worded identically on the homepage and the Sales dashboard.
 */
trait FormatsKpiValues
{
    private function money(float $amount): string
    {
        $formatter = new NumberFormatter('id_ID', NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($amount, 'IDR');
    }

    private function targetDescription(float $actual, float $target, string $label): string
    {
        if ($target <= 0) {
            return 'No '.$label.' target set';
        }

        return number_format(($actual / $target) * 100, 1).'% of '.$this->money($target).' target';
    }

    private function achievementColor(float $actual, float $target): string
    {
        if ($target <= 0) {
            return 'gray';
        }

        return match (true) {
            $actual >= $target => 'success',
            $actual >= $target * 0.75 => 'warning',
            default => 'danger',
        };
    }

    private function orderTrend(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? 'No orders last month' : 'No orders last month either';
        }

        $delta = (($current - $previous) / $previous) * 100;

        return number_format($previous).' last month ('.($delta >= 0 ? '+' : '').number_format($delta, 0).'%)';
    }

    private function winRateColor(?float $rate): string
    {
        return match (true) {
            $rate === null => 'gray',
            $rate >= 50 => 'success',
            $rate >= 25 => 'warning',
            default => 'danger',
        };
    }
}
