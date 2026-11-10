<?php

/*
|--------------------------------------------------------------------------
| Cookie settings page
|--------------------------------------------------------------------------
|
| Shown on /cookie-settings. :service is filled in by App\Support\LegalText.
| Keep this list in step with the cookies the app really sets (see the
| Privacy notice): only the ones the service needs, no tracking.
|
*/

return [
    'summary' => ':service uses only the cookies it needs to work. There are no analytics, advertising or tracking cookies, so there is nothing to switch on or off here.',

    'sections' => [
        [
            'title' => 'Session cookie',
            'body' => 'Keeps you logged in while you move between pages, and remembers the exam you chose. It is removed when you log out or after a period without activity.',
        ],
        [
            'title' => 'Security cookie (XSRF-TOKEN)',
            'body' => 'Protects the forms of the site (login, answers, comments, subscription requests) against requests sent from other websites.',
        ],
        [
            'title' => '"Remember me" cookie',
            'body' => 'Set only if you tick "Remember me" when you log in, so you stay logged in on that device. Logging out removes it.',
        ],
        [
            'title' => 'Sidebar cookie',
            'body' => 'Remembers whether the sidebar of the student app is open or closed.',
        ],
        [
            'title' => 'Light or dark mode',
            'body' => 'Your light or dark mode choice is kept in the local storage of your browser, not in a cookie, and is never sent to us.',
        ],
        [
            'title' => 'Removing cookies',
            'body' => 'You can delete cookies in your browser settings at any time. Without the session and security cookies you cannot log in or use the question bank.',
        ],
    ],
];
