<?php

/*
|--------------------------------------------------------------------------
| Permission catalogue (LLD 02 §2, SRS Appendix B)
|--------------------------------------------------------------------------
|
| Console areas and their actions. Department admins can delegate the
| "view" and "edit" actions inside their department's ceiling; "approve"
| and "members.manage" belong to department admins only; reserved
| permissions belong to the super admin and never appear in a ceiling.
|
*/

return [
    'areas' => [
        'teams' => ['view', 'view.all', 'edit', 'approve'],
        'events' => ['view', 'edit', 'approve'],
        'catalogue' => ['view', 'edit', 'approve'],
        'content' => ['view', 'edit', 'approve'],
        'inbox' => ['view', 'edit'],
        'finance' => ['view', 'edit', 'approve'],
        'reports' => ['view', 'edit'],
    ],

    'department_admin_only' => ['members.manage'],

    'reserved' => [
        'departments.manage',
        'tiers.manage',
        'settings.manage',
        'backups.run',
        'users.impersonate',
    ],

    /*
    | Team-scope permissions carried by the four team roles (LLD 03 §2).
    */
    'team_roles' => [
        'captain' => [
            'team.view', 'team.settings', 'members.invite', 'members.manage',
            'projects.edit', 'robots.edit', 'projects.publish', 'catalogue.suggest',
            'events.apply', 'budget.manage', 'team.transfer', 'team.archive',
        ],
        'coach' => [
            'team.view', 'team.settings', 'members.invite', 'members.manage',
            'projects.edit', 'robots.edit', 'projects.publish', 'catalogue.suggest',
            'events.apply', 'budget.manage',
        ],
        'member' => ['team.view', 'projects.edit', 'robots.edit', 'catalogue.suggest'],
        'viewer' => ['team.view'],
    ],
];
