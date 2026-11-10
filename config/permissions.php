<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Panel permissions
    |--------------------------------------------------------------------------
    |
    | Every permission the code checks. Permissions are defined here, because
    | each one guards a feature in the code; roles and the permissions given to
    | them are managed by an admin in Users and billing → Roles.
    |
    | The role list below is only the starting point: the seeder gives a new
    | role these permissions, and gives an existing role a permission that was
    | added later. It never takes away what an admin changed in the panel.
    |
    | An admin always has every permission. An editor starts with the content
    | permissions and never sees billing, user accounts or system settings.
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
        'content.atlas.manage',
        'content.textbooks.manage',
        'content.flashcards.manage',
        'community.comments.moderate',
        'billing.users.view',
        'billing.users.manage',
        'billing.subscriptions.view',
        'billing.subscriptions.manage',
        'billing.payments.view',
        'billing.plans.manage',
        'system.settings.manage',
        'system.privacy.view',
        'system.roles.manage',
    ],

    /*
    | What each permission allows, shown when an admin edits a role.
    */
    'labels' => [
        'content.exams.manage' => 'Manage Qbanks and exams',
        'content.categories.manage' => 'Manage subjects',
        'content.papers.manage' => 'Manage past papers',
        'content.questions.view' => 'View MCQs (also opens every student page without a subscription)',
        'content.questions.create' => 'Create MCQs and bulk uploads',
        'content.questions.update' => 'Edit MCQs and resolve review flags',
        'content.questions.publish' => 'Publish MCQs',
        'content.questions.delete' => 'Archive MCQs',
        'content.imports.view' => 'View bulk uploads',
        'content.atlas.manage' => 'Manage the Image Atlas',
        'content.textbooks.manage' => 'Manage the textbook',
        'content.flashcards.manage' => 'Manage flashcards',
        'community.comments.moderate' => 'Moderate comments and read student feedback',
        'billing.users.view' => 'View user accounts and student activity',
        'billing.users.manage' => 'Add, edit and delete user accounts',
        'billing.subscriptions.view' => 'View subscriptions',
        'billing.subscriptions.manage' => 'Activate, extend and cancel subscriptions',
        'billing.payments.view' => 'View payments',
        'billing.plans.manage' => 'Manage plans and prices',
        'system.settings.manage' => 'Manage system settings',
        'system.privacy.view' => 'Read the privacy notice',
        'system.roles.manage' => 'Manage roles and give roles to users',
    ],

    /*
    | Roles the code relies on by name. They cannot be renamed or deleted.
    */
    'protected_roles' => ['admin', 'editor', 'student'],

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
            'content.atlas.manage',
            'content.textbooks.manage',
            'content.flashcards.manage',
            'community.comments.moderate',
            'system.privacy.view',
        ],

        'admin' => ['*'],
    ],
];
