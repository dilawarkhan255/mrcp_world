<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Panel permissions
    |--------------------------------------------------------------------------
    |
    | Every permission the admin panel checks. The role list below is the single
    | place where a role is given a permission, so a new role only has to be
    | added here and to the role seeder.
    |
    | An admin has every permission. An editor can work with the content but
    | never sees billing, user accounts or system settings.
    |
    */

    'list' => [
        'content.exams.manage',
        'content.categories.manage',
        'content.papers.manage',
        'content.questions.view',
        'content.questions.create',
        'content.questions.update',
        'content.questions.publish',
        'content.questions.delete',
        'content.imports.view',
        'community.comments.moderate',
        'billing.users.view',
        'billing.users.manage',
        'billing.subscriptions.view',
        'billing.payments.view',
        'billing.plans.manage',
        'system.settings.manage',
        'system.privacy.view',
    ],

    'roles' => [
        'student' => [],

        'editor' => [
            'content.exams.manage',
            'content.categories.manage',
            'content.papers.manage',
            'content.questions.view',
            'content.questions.create',
            'content.questions.update',
            'content.questions.delete',
            'content.imports.view',
            'community.comments.moderate',
            'system.privacy.view',
        ],

        'admin' => ['*'],
    ],
];
