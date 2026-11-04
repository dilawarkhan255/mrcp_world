<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-map" heading="Publishing workflow" description="From an empty subject to questions students can practise.">
        <ol class="mw-workflow">
            @foreach ($this->getSteps() as $item)
                <li class="mw-workflow__step">
                    <div class="mw-workflow__head">
                        <span class="mw-workflow__badge">{{ $item['step'] }}</span>
                        <x-filament::icon :icon="$item['icon']" class="mw-workflow__icon" />
                    </div>
                    <p class="mw-workflow__title">{{ $item['title'] }}</p>
                    <p class="mw-workflow__body">{{ $item['body'] }}</p>
                    <x-filament::link :href="$item['href']" icon="heroicon-m-arrow-right" icon-position="after" size="sm">
                        {{ $item['action'] }}
                    </x-filament::link>
                </li>
            @endforeach
        </ol>
    </x-filament::section>
</x-filament-widgets::widget>
