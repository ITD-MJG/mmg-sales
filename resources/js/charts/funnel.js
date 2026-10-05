import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';
import { FunnelController, TrapezoidElement } from 'chartjs-chart-funnel';

// The ESM build of chartjs-chart-funnel has no side effects, so nothing is
// registered for us. Bar + BarElement are still needed: the funnel controller
// extends BarController and its elements inherit from BarElement.
Chart.register(
    FunnelController,
    TrapezoidElement,
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
);

/**
 * Draw a funnel chart onto a canvas.
 *
 * The band order is the dataset order, always. chartjs-chart-funnel sorts
 * nothing by value, so New → Contacted → Converted → Disqualified stays put
 * even when a later status outnumbers an earlier one.
 */
export function renderFunnel(canvas, { labels, values, colors }) {
    // Inherit the surrounding text colour, so the legend and axis labels track
    // Filament's light and dark themes instead of Chart.js' grey default.
    const textColor =
        getComputedStyle(canvas).color ||
        (document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#111827');

    return new Chart(canvas.getContext('2d'), {
        type: 'funnel',
        data: {
            labels,
            datasets: [
                {
                    data: values,
                    backgroundColor: colors,
                    hoverBackgroundColor: colors,
                    // Shrink the trapezoid inward from both edges rather than
                    // anchoring at the top, so the bands stay centred.
                    shrinkAnchor: 'middle',
                },
            ],
        },
        options: {
            // Filament's own chart component drives the canvas from the frame's
            // CSS aspect-ratio with `responsive: false`; letting Chart.js resize
            // itself here fights that frame and the two grow each other.
            responsive: false,
            maintainAspectRatio: false,
            indexAxis: 'y',
            // Chart.js draws to a canvas, so unlike the ApexCharts widgets it
            // cannot inherit Filament's theme colours on its own. Read them off
            // the canvas element, which does sit in the themed DOM.
            color: textColor,
            // The CRM ships its own section chrome, so the chart draws no
            // title of its own.
            plugins: {
                // The funnel is a single dataset, so the default legend would
                // emit one entry named after that dataset ("undefined" when it
                // has no label). The bands are the meaningful categories, so
                // build one entry per band from the labels and colours.
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        generateLabels: (chart) => {
                            const dataset = chart.data.datasets[0];

                            return (chart.data.labels ?? []).map((label, index) => ({
                                text: `${label} (${dataset.data[index]})`,
                                fillStyle: dataset.backgroundColor[index],
                                // Chart.js paints each label with `legendItem.fontColor`
                                // and leaves it untouched when absent, so a custom
                                // generateLabels has to carry the colour itself.
                                fontColor: textColor,
                                strokeStyle: dataset.backgroundColor[index],
                                lineWidth: 0,
                                hidden: false,
                                index,
                            }));
                        },
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (context) => ` ${context.label}: ${context.parsed.x}`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        // Lead counts are whole numbers; decimal steps read as
                        // noise.
                        precision: 0,
                    },
                },
                y: {
                    grid: { display: false },
                },
            },
        },
    });
}
