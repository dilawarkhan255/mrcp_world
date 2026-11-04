{{-- Runs before Filament reads its theme: a Day/Dark choice made in the
     student app ("appearance") is applied to the admin panel ("theme"). --}}
<script>
    (function () {
        try {
            var appearance = localStorage.getItem('appearance');
            if (appearance === 'light' || appearance === 'dark' || appearance === 'system') {
                localStorage.setItem('theme', appearance);
            }
        } catch (error) {
            // Storage can be blocked; Filament then uses its default.
        }
    })();
</script>
