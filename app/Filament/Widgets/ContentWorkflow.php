<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ContentWorkflow extends Widget
{
    protected static string $view = 'filament.widgets.content-workflow';

    protected static bool $isLazy = false;

    /** @var int|string|array<string, int> */
    protected int|string|array $columnSpan = 'full';
}
