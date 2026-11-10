<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Privacy notice
    |--------------------------------------------------------------------------
    |
    | The same notice is shown on the public privacy page and inside the admin
    | panel, so the panel and the site never disagree about what is stored.
    | :service, :owner, :email and :address come from config/legal.php
    | (App\Support\LegalText). A draft: have it reviewed before going live.
    |
    */

    'summary' => 'This notice explains what :service stores about you, why, who can see it and the choices you have. We do not sell your data, we do not show adverts and we do not use tracking or advertising cookies.',

    'sections' => [
        [
            'title' => 'Who is responsible',
            'body' => ':owner, :address, runs :service and is responsible for your data. Contact: :email.',
        ],
        [
            'title' => 'What we store',
            'body' => 'Account: your name, email address, a hash of your password (never the password itself), whether your email is verified, and the exam you choose. Study data: the sessions and mock exams you start, every answer you submit and how long it took, your bookmarks and notes, the questions you mark as read, and the comments and error reports you send. Subscriptions: the Qbank, plan, price, dates, status, how you paid and, for a manual payment, the payment reference and note you give us; for a card payment, the Stripe payment IDs (never your card number). Leaderboard: the display name you choose, only if you join. Technical data: the session and security cookies below, and the server logs that every web server keeps, including IP addresses.',
        ],
        [
            'title' => 'Why we store it',
            'body' => 'To give you the service you signed up for: log you in, score your answers, keep your progress, build your performance statistics and study plan, open the Qbanks you have paid for, and reply to you. To keep the service safe: limit login attempts and requests, and protect the content from copying. To improve the content: your error reports, and anonymous totals of how often each answer is chosen.',
        ],
        [
            'title' => 'Who can see it',
            'body' => 'You see your own answers, sessions, notes and subscriptions. Other students see only your comments (with your account name) and, if you join the leaderboard, your display name and weekly score. Editors work on the question bank; they see email addresses masked and cannot open billing records. Administrators can open subscription records and, when a support case needs it, the details of a single account. Other students\' answers are only ever shown as anonymous totals, such as the share of candidates who chose each option.',
        ],
        [
            'title' => 'The AI tutor',
            'body' => 'When you ask the AI tutor a question, we send your question and the MCQ (stem and options; after you have answered, also the correct answer and the explanation) to our AI provider, Anthropic, to generate the reply. For the AI study plan we send your subject names and scores. We do not send your name or email address. Replies are stored for a while so that the same question can be answered again without a new request. Do not type personal or patient details into the AI tutor.',
        ],
        [
            'title' => 'Card payments',
            'body' => 'Card payments are handled by Stripe on its own secure payment page. Your card details go to Stripe, not to us; Stripe tells us whether the payment succeeded, and we keep the payment IDs, the amount and the date with your subscription. Stripe processes your payment under its own privacy policy.',
        ],
        [
            'title' => 'Watermark and copy protection',
            'body' => 'To protect the content, the web app blocks copying and shows a faint watermark with your email address and the date on the pages you view. The watermark is only drawn in your browser; it is not stored as a separate record.',
        ],
        [
            'title' => 'Cookies and browser storage',
            'body' => 'We use only the cookies the service needs: a session cookie that keeps you logged in, a security cookie (XSRF-TOKEN) that protects forms, a "remember me" cookie if you tick that box, and a cookie that remembers whether the sidebar is open. Your light or dark mode choice is kept in your browser\'s local storage. We use no analytics, advertising or tracking cookies.',
        ],
        [
            'title' => 'How long we keep it',
            'body' => 'We keep your data while your account is open. When you delete your account in Settings, your account, sessions, answers, notes, bookmarks, comments, reports and subscription records are deleted. Totals that already counted your answers (for example, how many candidates chose option B) stay as anonymous numbers that cannot be linked back to you.',
        ],
        [
            'title' => 'Your rights',
            'body' => 'You can ask for a copy of your data, ask us to correct it, ask us to delete it, or object to how we use it. You can delete your account yourself in Settings. For anything else, write to :email and we will answer within 30 days. If you are not happy with our answer, you can complain to the data protection authority in your country.',
        ],
        [
            'title' => 'Changes to this notice',
            'body' => 'We will update this notice when the service changes; the date below shows the latest version.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin panel rules
    |--------------------------------------------------------------------------
    |
    | The panel follows the notice above. An editor sees masked email
    | addresses, and the panel never shows a student password. Study data is
    | kept while the account is open and deleted with it.
    |
    */

    'mask_email_for_editors' => true,

    'admin_visible_fields' => [
        'name',
        'email',
        'country',
        'timezone',
        'created_at',
    ],
];
