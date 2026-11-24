<x-filament-widgets::widget>
    <x-filament::section :compact="true" :contained="false">
        <div class="flex flex-wrap items-center justify-end gap-3">
            @foreach ($destinations as $destination)
                <x-filament::button
                    :href="$destination['url']"
                    :icon="$destination['icon']"
                    :tooltip="$destination['description']"
                    tag="a"
                >
                    {{ $destination['label'] }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
