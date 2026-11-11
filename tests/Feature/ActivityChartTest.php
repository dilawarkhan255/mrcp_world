<?php

it('lets the activity bars take their height from full-height columns', function () {
    // Regression: with items-end the columns had no height, so every bar (a % height) was 0 px tall.
    foreach (['js/pages/Dashboard.vue', 'js/pages/questions/Performance.vue'] as $page) {
        $source = file_get_contents(resource_path($page));
        expect($source)->toContain('items-stretch gap-1.5')->not->toMatch('/flex h-(24|40) items-end gap-1\.5/');
    }
});
