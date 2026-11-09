<x-filament::modal
    id="incomplete-lead-warning"
    icon="heroicon-o-exclamation-triangle"
    icon-color="warning"
    width="md"
    :close-button="false"
    :close-by-clicking-away="false"
    :close-by-escaping="false"
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
                class="w-full"
                x-on:click="$dispatch('close-modal', { id: 'incomplete-lead-warning' })"
            >
                Update Lead
            </x-filament::button>
        @else
            <x-filament::button
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'incomplete-lead-warning' })"
            >
                Close
            </x-filament::button>
        @endif
    </x-slot>
</x-filament::modal>
