<?php

return [
    'status' => [
        'draft' => 'Draft',
        'open' => 'Open',
        'closed' => 'Closed',
        'running' => 'Running',
        'finished' => 'Finished',
        'archived' => 'Archived',
    ],
    'visibility' => [
        'public' => 'Public',
        'invite_only' => 'Invite only',
    ],
    'registration_status' => [
        'invited' => 'Invited',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'waitlisted' => 'Waitlisted',
        'rejected' => 'Rejected',
        'withdrawn' => 'Withdrawn',
    ],
    'rules' => [
        'team_inactive' => 'The team is not active.',
        'feature_off' => 'This team’s tier does not include event registration.',
        'not_visible' => 'This event is by invitation only.',
        'window_closed' => 'Registration for this event is not open.',
        'event_closed' => 'Teams can only be added while the event is open, closed for registration or running.',
        'duplicate' => 'The team already has an entry in this category.',
        'members_count' => 'This category needs between :min and :max members.',
        'member_not_in_team' => 'Not in the team: :names.',
        'member_no_birthdate' => 'Birthdate missing for: :names.',
        'member_age' => 'Outside the age range :min–:max on the event day: :names.',
    ],
    'errors' => [
        'withdraw_closed' => 'Registration has closed, so only the organisers can withdraw this entry now.',
        'still_full' => 'The event or category is still full.',
        'category_mismatch' => 'This category belongs to another event.',
        'not_invited' => 'This entry is not waiting for the team to accept.',
        'needs_category' => 'Add at least one category before opening the event.',
        'category_has_entries' => 'These categories already have entries and cannot be removed: :names.',
        'results_not_finished' => 'Results can be recorded once the event has finished.',
        'result_not_approved' => 'Results can only be recorded for approved entries of this event.',
        'delete_not_draft' => 'Only draft events can be deleted.',
    ],
];
