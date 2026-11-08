<?php

return [
    'post_status' => [
        'draft' => 'Draft',
        'in_review' => 'In review',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'archived' => 'Archived',
    ],
    'message_status' => [
        'new' => 'New',
        'in_progress' => 'In progress',
        'closed' => 'Closed',
    ],
    'errors' => [
        'archived' => 'Archived posts cannot be edited.',
        'not_draft' => 'Only drafts can be sent for review.',
        'cannot_publish' => 'This post cannot be published from its current status.',
        'department_not_allowed' => 'Choose a department where you can edit news.',
        'assignee_cannot_view' => 'Assign someone who can read this department’s inbox.',
    ],
    'flash' => [
        'created' => 'Draft created.',
        'saved' => 'Post saved.',
        'submitted' => 'Sent for review.',
        'published' => 'Post published.',
        'scheduled' => 'Post scheduled.',
        'unpublished' => 'Post moved back to draft.',
        'removed' => 'Post removed.',
        'updated' => 'Message updated.',
        'note_added' => 'Note added.',
        'settings_saved' => 'Settings saved.',
    ],
    'mail' => [
        'subject' => 'New message: :subject',
        'intro' => ':name wrote through the website:',
        'action' => 'Open the inbox',
    ],
];
