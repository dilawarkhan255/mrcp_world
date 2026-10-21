<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Privacy notice
    |--------------------------------------------------------------------------
    |
    | The same notice is shown on the public privacy page and inside the admin
    | panel, so the panel and the site never disagree about what is stored.
    |
    */

    'summary' => 'We store the account details you give us, the answers you submit and the sessions you practise. Nothing is sold and nothing is shared with advertisers.',

    'sections' => [
        [
            'title' => 'What we store',
            'body' => 'Your name, email address and password hash, the exams you pick, every answer you submit, the sessions and mock exams you start, and the notes you write on your bookmarks.',
        ],
        [
            'title' => 'Why we store it',
            'body' => 'The answers are needed to score you, build your history and compare your mock exam score with other candidates. The account details are needed to let you log in and to reach you about the service.',
        ],
        [
            'title' => 'Who can see it',
            'body' => 'Students see their own answers, sessions and notes. Editors can work with the question bank but cannot open student accounts or billing records. Administrators can open billing records and, when a support case needs it, the account details of a single student.',
        ],
        [
            'title' => 'How long we keep it',
            'body' => 'Study data is kept while your account is open. When an account is closed the personal details are removed and the anonymous answers are kept in the score statistics so the mock exam benchmarks stay useful.',
        ],
        [
            'title' => 'Your choices',
            'body' => 'You can ask for a copy of your data, correct it, or ask for it to be deleted. Contact us and we will answer within thirty days.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin panel rules
    |--------------------------------------------------------------------------
    |
    | The panel follows the notice above. An editor sees masked email
    | addresses, the retention period is shown on the privacy page, and the
    | panel never shows a student password.
    |
    */

    'retention_days' => 730,

    'mask_email_for_editors' => true,

    'admin_visible_fields' => [
        'name',
        'email',
        'country',
        'timezone',
        'created_at',
    ],
];
