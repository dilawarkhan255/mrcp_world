<?php

return [
    'groups' => [
        ['title' => 'Home', 'icon' => 'LayoutGrid', 'items' => [['title' => 'Home', 'href' => '/dashboard']]],
        ['title' => 'Questions', 'icon' => 'ListChecks', 'items' => [
            ['title' => 'Question bank', 'href' => '/questions/bank'],
            ['title' => 'Past papers', 'href' => '/questions/past-papers'],
            ['title' => 'Previous sessions', 'href' => '/questions/sessions'],
            ['title' => 'Mock exams', 'href' => '/questions/mocks'],
            ['title' => 'Review questions', 'href' => '/questions/review'],
            ['title' => 'Performance', 'href' => '/questions/performance'],
            ['title' => 'Comment threads', 'href' => '/questions/comments'],
        ]],
        ['title' => 'Textbooks', 'icon' => 'BookOpen', 'items' => [
            ['title' => 'High-yield', 'href' => '/textbooks/high-yield'],
            ['title' => 'Extended', 'href' => '/textbooks/extended'],
        ]],
        ['title' => 'Knowledge tutor', 'icon' => 'Brain', 'items' => [
            ['title' => 'Flashcards', 'href' => '/tutor/flashcards'],
            ['title' => 'Review facts', 'href' => '/tutor/facts'],
        ]],
        ['title' => 'My revision', 'icon' => 'Bookmark', 'items' => [
            ['title' => 'Bookmarks and notes', 'href' => '/revision/bookmarks'],
        ]],
    ],
    'mocks' => [
        'presets' => [
            ['title' => 'Quick check', 'questions' => 10, 'minutes' => 10],
            ['title' => 'Short mock', 'questions' => 20, 'minutes' => 30],
            ['title' => 'Full mock', 'questions' => 40, 'minutes' => 90],
        ],
        'max_questions' => 100,
        'max_minutes' => 180,
    ],
];
