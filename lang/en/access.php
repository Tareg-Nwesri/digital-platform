<?php

return [
    'spam' => [
        'rejected' => 'We could not verify this request. Please wait a moment and try again.',
        'turnstile_failed' => 'Please complete the security check.',
    ],
    'impersonation' => [
        'blocked' => 'This action is not available while acting as another user.',
    ],
    'roles' => [
        'captain' => 'Captain',
        'coach' => 'Coach',
        'member' => 'Member',
        'viewer' => 'Viewer',
        'department-admin' => 'Department admin',
        'department-member' => 'Department member',
    ],
    'invitations' => [
        'invalid_role' => 'This role cannot be used for this workspace.',
        'already_member' => 'This person is already a member.',
        'not_pending' => 'This invitation is no longer valid.',
        'invalid' => 'This invitation link is invalid or has expired.',
        'password_to_confirm' => 'Enter your current password to accept.',
        'accepted' => 'Welcome! Your invitation has been accepted.',
        'mail' => [
            'subject' => 'You are invited to join :name',
            'greeting' => 'Hello!',
            'intro' => 'You have been invited to join :name as :role on the Digital Robotics platform.',
            'action' => 'Accept invitation',
            'expires' => 'This invitation expires on :date.',
        ],
    ],
    'users' => [
        'activated' => ':name can sign in again.',
        'deactivated' => ':name has been deactivated and signed out.',
    ],
    'inactive' => 'This account has been deactivated. Contact the platform administrator.',
];
