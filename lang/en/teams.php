<?php

return [
    'status' => [
        'pending' => 'Pending',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'archived' => 'Archived',
    ],
    'roles' => [
        'captain' => 'Captain',
        'coach' => 'Coach',
        'member' => 'Member',
        'viewer' => 'Viewer',
    ],
    'errors' => [
        'reason_required' => 'A reason is required for this status change.',
        'offline_role' => 'Members without a login can only be members or viewers.',
        'already_member' => 'This person is already in the team.',
        'not_member' => 'This person is not in the team.',
        'limit_reached' => 'The team has reached its limit of :limit members.',
        'one_captain' => 'A team can only have one captain. Transfer captaincy instead.',
        'use_transfer' => 'Use "transfer captaincy" to change the captain.',
        'captain_needs_login' => 'The new captain must be a team member with a login.',
        'remove_captain' => 'The captain cannot be removed. Transfer captaincy first.',
    ],
];
