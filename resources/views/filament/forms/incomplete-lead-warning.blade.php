<x-filament::modal
    id="incomplete-lead-warning"
    icon="heroicon-o-exclamation-triangle"
    icon-color="warning"
    width="md"
>
    <x-slot name="heading">
        Lead details are incomplete
    </x-slot>

    <x-slot name="description">
        Expected Revenue and Closing Date are required for this lead. Update the lead first so activity reports stay accurate.
    </x-slot>

    <x-slot name="footerActions">
        @if ($editUrl)
            <x-filament::button
                tag="a"
                :href="$editUrl"
                color="warning"
                icon="heroicon-m-pencil-square"
                target="_blank"
            >
                Update Lead
            </x-filament::button>
        @endif

        <x-filament::button
            color="gray"
            x-on:click="$dispatch('close-modal', { id: 'incomplete-lead-warning' })"
        >
            {{ $editUrl ? 'Continue Anyway' : 'Close' }}
        </x-filament::button>
    </x-slot>
</x-filament::modal>
