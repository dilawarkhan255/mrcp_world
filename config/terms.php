<?php

/*
|--------------------------------------------------------------------------
| Terms of use
|--------------------------------------------------------------------------
|
| Shown on /terms. :service, :owner, :email, :address and :law are filled
| in from config/legal.php (App\Support\LegalText). This is a draft written
| to match what the app does; have it reviewed before the site goes live.
|
*/

return [
    'summary' => 'These terms apply when you use :service, including the question banks, past paper MCQs, mock exams, the extended textbook and the AI tutor. By creating an account or subscribing you agree to them.',

    'sections' => [
        [
            'title' => 'Who we are',
            'body' => ':service is run by :owner, :address. You can reach us at :email.',
        ],
        [
            'title' => 'What the service is',
            'body' => ':service is a revision tool for MRCP Part 1 and MRCP Part 2. It offers multiple-choice questions with explanations, past paper MCQs, timed mock exams, an extended textbook, an image atlas, performance statistics and an AI tutor. It is not affiliated with or endorsed by the Royal Colleges of Physicians or any exam board.',
        ],
        [
            'title' => 'Educational use only, not medical advice',
            'body' => 'The content is written to help you prepare for exams. It is not clinical advice and must not be used to diagnose or treat patients. Explanations refer to NICE and WHO guidance where it applies, but guidance changes: always check the current guideline before acting on it. We do not promise that using the service will make you pass an exam.',
        ],
        [
            'title' => 'Your account',
            'body' => 'You need an account to subscribe. Give us accurate details and keep your password private. An account is for one person only: sharing it, or letting others use it, is not allowed. You are responsible for what is done with your account. You can delete your account at any time in Settings.',
        ],
        [
            'title' => 'Subscriptions and payment',
            'body' => 'Each Qbank is sold separately: MRCPWorld 1 Qbank (MRCP 1) and MRCPWorld 2 Qbank (MRCP 2). The price and the length of access are shown on the pricing page when you subscribe. You can pay by card on the secure payment page of our payment provider, Stripe, or another way we offer. Access starts when your payment is confirmed and your subscription is activated, and ends at the end of the period shown in your account. A subscription does not renew on its own; to keep access, buy a new period before it ends, and the new period is added to the time you have left. Without a subscription you can use the free demo questions only.',
        ],
        [
            'title' => 'Refunds',
            'body' => 'Because access starts as soon as your subscription is activated, a subscription you have started to use is not refunded, except where the law gives you a right to a refund. If you were charged by mistake, or you paid but could not get access, contact us at :email within 14 days of payment and we will put it right or refund you.',
        ],
        [
            'title' => 'How you may use the content',
            'body' => 'The questions, explanations, textbook chapters, images and other content belong to :owner or to those who licensed them to us. Your subscription lets you use them for your own revision only. You must not copy, download, record, scrape, print, share, publish, sell or reuse any of the content, in whole or in part, or use it to train or build another product. The web app blocks copying and adds a watermark with your email address to the pages you view, so that leaked content can be traced to the account it came from.',
        ],
        [
            'title' => 'Fair use',
            'body' => 'Do not try to get around the copy protection, the access checks or the usage limits; do not use bots or automated tools to read the site; do not interfere with the service or other users; and do not post anything unlawful, offensive or misleading in comments or reports.',
        ],
        [
            'title' => 'Comments, notes and reports',
            'body' => 'Your notes and bookmarks are private. Comments you post on a question are shown to other students with your account name, and you keep responsibility for them. We may hide or remove comments that break these terms. When you report an error in a question, you let us use your report to improve the content.',
        ],
        [
            'title' => 'AI tutor',
            'body' => 'The AI tutor answers your questions about an MCQ using an AI model. Its answers are generated automatically and can be wrong or incomplete: check them against the explanation and the current guideline. The number of AI requests per day is limited. The AI tutor is not available during an open mock exam.',
        ],
        [
            'title' => 'Leaderboard',
            'body' => 'The leaderboard is optional. You appear on it only after you choose to join and pick a display name in Settings. We may remove a display name that is offensive or that identifies someone else.',
        ],
        [
            'title' => 'Suspending or closing accounts',
            'body' => 'We may suspend or close an account that shares access, copies or redistributes content, or otherwise breaks these terms. If we close an account because of such a breach, the remaining subscription time is not refunded.',
        ],
        [
            'title' => 'Availability and changes',
            'body' => 'We work to keep the service available and the content accurate, but the service is provided as it is: it may sometimes be unavailable, and content may be changed, corrected or removed. We may update these terms; the date below shows the latest version, and important changes will be announced in the app.',
        ],
        [
            'title' => 'Liability',
            'body' => 'Nothing in these terms limits liability that cannot be limited by law. Otherwise, we are not liable for exam results, for decisions you make based on the content, or for indirect losses, and our total liability to you is limited to the amount you paid us in the 12 months before the claim.',
        ],
        [
            'title' => 'Law and contact',
            'body' => 'These terms are governed by the law of :law. Questions about these terms: :email.',
        ],
    ],
];
