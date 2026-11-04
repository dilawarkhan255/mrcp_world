<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ContentWorkflow extends Widget
{
    protected static string $view = 'filament.widgets.content-workflow';

    protected static ?int $sort = 6;

    protected static bool $isLazy = false;

    /** @var int|string|array<string, int> */
    protected int|string|array $columnSpan = 'full';

    /** @return list<array{step: int, title: string, body: string, href: string, action: string, icon: string}> */
    public function getSteps(): array
    {
        return [
            ['step' => 1, 'title' => 'Subjects', 'body' => 'Create the subjects of each exam. The subject slug goes in every import row.', 'href' => '/admin/categories', 'action' => 'Manage subjects', 'icon' => 'heroicon-o-rectangle-stack'],
            ['step' => 2, 'title' => 'Past papers', 'body' => 'Create a paper for past-paper MCQs and pick it when adding or importing its questions.', 'href' => '/admin/past-papers', 'action' => 'Manage papers', 'icon' => 'heroicon-o-document-text'],
            ['step' => 3, 'title' => 'Add MCQs', 'body' => 'Write single MCQs, or download the template and bulk upload CSV / XLSX files.', 'href' => '/admin/question-imports', 'action' => 'Bulk upload', 'icon' => 'heroicon-o-arrow-up-tray'],
            ['step' => 4, 'title' => 'Review & publish', 'body' => 'Check citations flagged for review, then publish the questions and the paper.', 'href' => '/admin/questions', 'action' => 'Open MCQs', 'icon' => 'heroicon-o-check-badge'],
        ];
    }
}
