<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Student sidebar, top to bottom.
    |--------------------------------------------------------------------------
    | Each item: title, icon (lucide-vue-next name), href (null for a parent
    | that only groups children), optional match (extra paths that keep the
    | item active) and optional children. Subjects of the current exam are
    | appended to "Question Bank" by App\Support\StudentNavigation.
    */
    'navigation' => [
        ['title' => 'Dashboard', 'icon' => 'LayoutGrid', 'href' => '/dashboard', 'match' => ['/questions/sessions']],
        ['title' => 'Question Bank', 'icon' => 'ListChecks', 'href' => null, 'subjects' => true, 'children' => [
            ['title' => 'Main MCQ Bank', 'icon' => 'Library', 'href' => '/questions/bank'],
            ['title' => 'Past Paper MCQs', 'icon' => 'BookOpen', 'href' => '/questions/past-papers'],
        ]],
        ['title' => 'Search MCQs', 'icon' => 'Search', 'href' => '/questions/search'],
        ['title' => 'Mock Tests', 'icon' => 'ClipboardList', 'href' => '/questions/mocks'],
        ['title' => 'Textbooks', 'icon' => 'BookOpen', 'href' => '/textbooks'],
        ['title' => 'My Reviews', 'icon' => 'RotateCcw', 'href' => '/questions/review'],
        ['title' => 'My Notes', 'icon' => 'Bookmark', 'href' => '/revision/notes', 'match' => ['/revision/bookmarks']],
        ['title' => 'Performance', 'icon' => 'TrendingUp', 'href' => '/questions/performance'],
        ['title' => 'Subscription', 'icon' => 'CreditCard', 'href' => '/subscription'],
        ['title' => 'Feedback', 'icon' => 'MessageSquare', 'href' => '/feedback'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages not built yet: href => title. Each one is registered as a route
    | that renders the "Coming soon" placeholder.
    |--------------------------------------------------------------------------
    */
    'placeholders' => [
        '/textbooks' => 'Textbooks',
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
