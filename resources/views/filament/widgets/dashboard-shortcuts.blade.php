<x-filament-widgets::widget>
    <x-filament::section
        heading="Jump to a dashboard"
        description="Sales covers targets, leaderboard and rep performance. Analytics covers revenue, principal and territory breakdowns."
        :compact="true"
    >
        <div class="flex flex-wrap items-center gap-3">
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
