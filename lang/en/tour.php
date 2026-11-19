<?php

/*
 * The "?" page guide. Each page's tour lives in resources/js/tours/tours.json
 * and points at these keys; add a group per page below.
 */
return [
    'ui' => [
        'button' => 'Page guide',
        'next' => 'Next',
        'previous' => 'Back',
        'done' => 'Done',
        'progress' => '{{current}} of {{total}}',
    ],

    'common' => [
        'sidebar' => [
            'title' => 'The side menu',
            'body' => 'Everything you can open is here, grouped by topic. You only see the areas your role allows. Use the arrow at the top to collapse it.',
        ],
        'switcher' => [
            'title' => 'Switch workspace',
            'body' => 'If you belong to several teams or departments, pick the one you want to work in here. The menu below changes with it.',
        ],
        'user_menu' => [
            'title' => 'Your account',
            'body' => 'Open your profile, security settings, appearance and language, your earnings, or sign out.',
        ],
        'guide' => [
            'title' => 'This guide, on every page',
            'body' => 'Press the ? button at the top of any page to get a tour of what that page does.',
        ],
    ],

    'dashboard' => [
        'intro' => [
            'title' => 'The organisation dashboard',
            'body' => 'A working overview of the whole platform: what waits for someone, how events are filling up, where the money stands and how the community is growing.',
        ],
        'attention' => [
            'title' => 'Needs your attention',
            'body' => 'Each card counts something waiting for a decision: registrations, new teams, funding, approvals, suggestions and messages. Click a card to open that queue. A green check means it is clear.',
        ],
        'events' => [
            'title' => 'Upcoming events',
            'body' => 'The next events with days to go, days until registration closes and a bar of approved and pending teams against capacity.',
        ],
        'registrations' => [
            'title' => 'Registrations, last 14 days',
            'body' => 'New event registrations per day. Hover a bar for the exact number; the total and how many came from the website form are above it.',
        ],
        'money' => [
            'title' => 'Money',
            'body' => 'Income and spending posted this month, what team and project budgets hold, and the balance of each fund.',
        ],
        'community' => [
            'title' => 'Community',
            'body' => 'Active and pending teams, team members, new teams this month, robots and published projects.',
        ],
        'activity' => [
            'title' => 'Recent activity',
            'body' => 'The latest actions across the platform and who did them. The full history is in the audit log.',
        ],
    ],

    'team_dashboard' => [
        'intro' => [
            'title' => 'Your team dashboard',
            'body' => 'A quick look at your team: members, projects, robots, what your parts cost and the events you have entered.',
        ],
        'tiles' => [
            'title' => 'Team at a glance',
            'body' => 'Members against your tier limit, published projects and drafts, active robots and the total cost of their parts lists.',
        ],
        'events' => [
            'title' => 'Upcoming events',
            'body' => 'Events your team has entered or been invited to, with the status of each entry. Click one to open it.',
        ],
    ],
];
