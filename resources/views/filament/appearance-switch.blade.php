{{-- Day / Dark switch in the admin sidebar, like the student app. Filament keeps
     the choice in localStorage "theme" and the student app in "appearance";
     both are written so the two sides always match. --}}
<div
    x-data="{
        mode: localStorage.getItem('theme') || 'system',
        get dark() {
            return this.mode === 'dark' || (this.mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
        },
        set(value) {
            this.mode = value
            localStorage.setItem('appearance', value)
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: value }))
        },
    }"
    class="mw-appearance"
>
    <span class="mw-appearance__label" x-show="$store.sidebar.isOpen">Appearance</span>
    <div class="mw-appearance__group" role="group" aria-label="Appearance" x-bind:class="{ 'mw-appearance__group--stacked': ! $store.sidebar.isOpen }">
        <button type="button" title="Day mode" x-bind:aria-pressed="(! dark).toString()" x-on:click="set('light')"
            class="mw-appearance__btn" x-bind:class="{ 'mw-appearance__btn--active': ! dark }">
            <x-filament::icon icon="heroicon-m-sun" class="h-4 w-4" />
            <span x-show="$store.sidebar.isOpen">Day</span>
        </button>
        <button type="button" title="Dark mode" x-bind:aria-pressed="dark.toString()" x-on:click="set('dark')"
            class="mw-appearance__btn" x-bind:class="{ 'mw-appearance__btn--active': dark }">
            <x-filament::icon icon="heroicon-m-moon" class="h-4 w-4" />
            <span x-show="$store.sidebar.isOpen">Dark</span>
        </button>
    </div>
</div>
