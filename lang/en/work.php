<?php

return [
    'project_status' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ],
    'robot_status' => [
        'design' => 'Design',
        'building' => 'Building',
        'ready' => 'Ready',
        'retired' => 'Retired',
    ],
    'availability' => [
        'available' => 'Available',
        'part_archived' => 'Part no longer in the catalogue',
        'offer_removed' => 'Price removed',
        'offer_out_of_stock' => 'Out of stock',
        'no_offer' => 'No price chosen',
    ],
    'errors' => [
        'robot_registered' => 'This robot is entered in an approved event registration and cannot be deleted.',
        'other_team_project' => 'Choose one of your own team’s projects.',
        'part_not_visible' => 'This part is not available to your team.',
        'offer_mismatch' => 'This price belongs to a different part.',
    ],
    'flash' => [
        'project_saved' => 'Project saved.',
        'project_published' => 'Project published.',
        'project_unpublished' => 'Project moved back to draft.',
        'project_deleted' => 'Project deleted.',
        'robot_saved' => 'Robot saved.',
        'robot_deleted' => 'Robot deleted.',
        'bom_saved' => 'Parts list saved.',
    ],
];
