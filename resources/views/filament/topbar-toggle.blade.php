{{-- The menu button of the top bar, on every screen size, like the student app.
     Filament's precompiled CSS has no responsive helpers such as lg:inline-flex,
     so its visibility comes from admin-styles.blade.php. --}}
<button
    type="button"
    x-data="{}"
    x-on:click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()"
    x-bind:aria-label="$store.sidebar.isOpen ? 'Close the menu' : 'Open the menu'"
    x-bind:aria-expanded="$store.sidebar.isOpen.toString()"
    class="mw-topbar-btn mw-topbar-toggle"
>
    <x-filament::icon icon="heroicon-o-bars-3" class="h-5 w-5" />
</button>
