<?php
// The chart bundle is loaded once per panel via a render hook, not here: this
// view renders inside a Livewire snapshot, where @vite would emit a malformed
// tag on the snapshot element and register the module twice.
?>

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section>
        <x-slot name="heading">
            Lead Status
        </x-slot>

        <div
            wire:ignore
            x-data="{
                chart: null,
                observer: null,
                init() {
                    this.chart = window.MmgCharts.renderFunnel($refs.canvas, @js($this->getChartData()));

                    // Filament's own chart component keeps the canvas in step with
                    // the frame through a ResizeObserver, because the chart is drawn
                    // with `responsive: false` and would otherwise keep its first
                    // size when the widget reflows.
                    this.observer = new ResizeObserver(() => this.chart?.resize());
                    this.observer.observe(this.$refs.frame);
                },
                destroy() {
                    this.observer?.disconnect();
                    this.chart?.destroy();
                },
            }"
        >
            {{-- The frame classes are Filament's own: they size the canvas from the
                 widget's width (aspect-[1.5]), so this chart matches the height of
                 the other dashboard charts instead of a hardcoded pixel value. --}}
            <div x-ref="frame" class="fi-wi-chart-frame fi-wi-chart-canvas-ctn">
                <canvas x-ref="canvas" style="width: 100%; height: 100%; max-height: 100%"></canvas>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
