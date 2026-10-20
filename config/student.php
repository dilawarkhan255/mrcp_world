<?php

return [
    'groups' => [
        ['title' => 'Home', 'icon' => 'LayoutGrid', 'items' => [['title' => 'Home', 'href' => '/dashboard']]],
        ['title' => 'Questions', 'icon' => 'ListChecks', 'items' => [
            ['title' => 'Question bank', 'href' => '/questions/bank'],
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
];
