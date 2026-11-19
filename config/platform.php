<?php

return [
    'invitation_days' => (int) env('INVITATION_DAYS', 7),

    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@digitalrobot.org'),
        'name' => env('SUPER_ADMIN_NAME', 'Platform Admin'),
    ],

    /*
    | Password for every @demo.test login created by DemoSeeder (local/staging only).
    */
    'demo_password' => env('DEMO_PASSWORD', 'password'),

    'currencies' => ['LYD', 'USD', 'EUR'],

    /*
    | Defaults for the settings table (LLD 08 §1). Stored values override these.
    */
    'settings' => [
        'inbox.department_id' => null,
        'events.auto_promote_waitlist' => true,
        'finance.second_approval_threshold' => 5_000_000,
        'finance.prize_split' => ['team' => 50, 'reinvestment' => 50],
        'finance.request_sources' => ['general_fund', 'reinvestment_pool'],
        'finance.department_id' => null,
        'public.home_sections' => ['teams', 'projects', 'robots', 'events', 'news', 'sponsors'],
        'public.contact_email_display' => null,
        'retention.months' => 24,
    ],
];
