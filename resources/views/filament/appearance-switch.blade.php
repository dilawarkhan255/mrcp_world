{{-- Day / Dark switch in the admin top bar, like the student app. Filament keeps
     the choice in localStorage "theme" and the student app in "appearance";
     both are written so the two sides always match. --}}
<button
    type="button"
    x-data="{
        mode: localStorage.getItem('theme') || 'system',
        get dark() {
            return this.mode === 'dark' || (this.mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
        },
        toggle() {
            const value = this.dark ? 'light' : 'dark'
            this.mode = value
            localStorage.setItem('appearance', value)
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: value }))
        },
    }"
    x-on:click="toggle()"
    x-bind:title="dark ? 'Switch to Day mode' : 'Switch to Dark mode'"
    x-bind:aria-label="dark ? 'Switch to Day mode' : 'Switch to Dark mode'"
    class="mw-topbar-btn mw-appearance"
>
    <x-filament::icon icon="heroicon-o-sun" class="h-5 w-5" x-show="dark" />
    <x-filament::icon icon="heroicon-o-moon" class="h-5 w-5" x-show="! dark" />
</button>
