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
        ['title' => 'My Notes', 'icon' => 'Bookmark', 'href' => '/revision/notes', 'match' => ['/revision/bookmarks'], 'subscription' => true],
        ['title' => 'Search MCQs', 'icon' => 'Search', 'href' => '/questions/search', 'subscription' => true],
        ['title' => 'Question Bank', 'icon' => 'ListChecks', 'href' => null, 'subjects' => true, 'children' => [
            ['title' => 'Main MCQ Bank', 'icon' => 'Library', 'href' => '/questions/bank'],
        ]],
        ['title' => 'Past Papers', 'icon' => 'FileClock', 'href' => '/questions/past-papers', 'subscription' => true],
        ['title' => 'Extended Textbook', 'icon' => 'BookOpen', 'href' => '/textbooks', 'match' => ['/textbooks/'], 'subscription' => true],
        ['title' => 'Image Atlas', 'icon' => 'Images', 'href' => '/image-atlas', 'subscription' => true],
        ['title' => 'Flashcards', 'icon' => 'Layers', 'href' => '/flashcards', 'match' => ['/flashcards/'], 'subscription' => true],
        ['title' => 'Review Questions', 'icon' => 'RotateCcw', 'href' => '/questions/review', 'subscription' => true],
        // A heading, not a link: the items below it are grouped under "More".
        ['title' => 'More', 'section' => 'More'],
        ['title' => 'Mock Tests', 'icon' => 'ClipboardList', 'href' => '/questions/mocks', 'subscription' => true],
        ['title' => 'Performance', 'icon' => 'TrendingUp', 'href' => '/questions/performance'],
        ['title' => 'Leaderboard', 'icon' => 'Trophy', 'href' => '/leaderboard', 'subscription' => true],
        ['title' => 'AI Study Plan', 'icon' => 'Sparkles', 'href' => '/study-plan', 'subscription' => true],
        ['title' => 'Knowledge Tutor', 'icon' => 'GraduationCap', 'href' => '/tutor', 'subscription' => true],
        ['title' => 'Subscription', 'icon' => 'CreditCard', 'href' => '/subscription'],
        ['title' => 'Feedback', 'icon' => 'MessageSquare', 'href' => '/feedback'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages not built yet: href => title. Each one is registered as a route
    | that renders the "Coming soon" placeholder.
    |--------------------------------------------------------------------------
    */
    'placeholders' => [],

    /*
    |--------------------------------------------------------------------------
    | Live image searches shown after a question is answered. {q} is replaced
    | by the question's URL-encoded search term. Change a URL here if a site
    | changes its search address.
    |--------------------------------------------------------------------------
    */
    'image_search' => [
        'radiopaedia' => ['label' => 'Radiopaedia', 'url' => 'https://radiopaedia.org/search?q={q}&scope=all'],
        'dermnet' => ['label' => 'DermNet', 'url' => 'https://dermnetnz.org/search?q={q}'],
        'litfl' => ['label' => 'Life in the Fast Lane', 'url' => 'https://litfl.com/?s={q}'],
        'eyewiki' => ['label' => 'EyeWiki', 'url' => 'https://eyewiki.org/w/index.php?search={q}'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Manual payment until a payment gateway is added. Shown on the
    | subscription page above the request form.
    |--------------------------------------------------------------------------
    */
    'subscription' => [
        'payment_instructions' => env('SUBSCRIPTION_PAYMENT_INSTRUCTIONS') ?: 'Pay the plan price through the payment method the MRCPWorld team gave you (ask in the WhatsApp group if you do not have it), then enter your payment reference below. Access opens once the team confirms the payment.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Leaderboard: students who opted in, ranked by correct first answers,
    | then accuracy. The week starts on Monday 00:00 in the timezone below.
    |--------------------------------------------------------------------------
    */
    'leaderboard' => [
        'min_answers' => 20,
        'size' => 50,
        'cache_minutes' => 10,
        'timezone' => 'Europe/London',
    ],

    /*
    |--------------------------------------------------------------------------
    | Study plan, built from the student's own answers (no AI service).
    |--------------------------------------------------------------------------
    */
    'study_plan' => [
        'daily_questions' => 30,
        'subjects_per_day' => 3,
        'weak_subjects' => 3,
        'min_answers' => 10,
        'days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Copy protection on every page, for students and guests: copying, cutting,
    | selecting, dragging, the context menu and printing are blocked, and a
    | signed-in student sees a faint watermark with their email. Content staff are never protected. This deters copying; it cannot
    | stop screenshots, which the watermark makes traceable.
    |--------------------------------------------------------------------------
    */
    'protection' => [
        'enabled' => (bool) env('CONTENT_PROTECTION', true),
        'watermark' => (bool) env('CONTENT_WATERMARK', true),
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
