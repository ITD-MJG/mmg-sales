import { renderFunnel } from './funnel';

// Filament widget views are rendered as HTML, so the render hook is exposed on
// the window and called from a small inline module in the Blade view. This
// keeps the chart code in the Vite bundle instead of inline in markup.
window.MmgCharts = { renderFunnel };

// The widget view loads this bundle with `defer`, so an Alpine component may
// already have initialised before these globals existed. Announce readiness so
// it can draw without a polling loop.
document.dispatchEvent(new Event('mmg-charts-ready'));
