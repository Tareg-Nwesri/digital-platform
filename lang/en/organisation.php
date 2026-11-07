<?php

return [
    'errors' => [
        'super_admin_only' => 'Only the super admin can change a department\'s permission ceiling.',
        'not_delegable' => 'The permission ":permission" cannot be delegated.',
        'outside_ceiling' => 'The permission ":permission" is outside this department\'s ceiling.',
        'admin_only' => 'Only an admin of this department can change member permissions.',
        'member_only' => 'Permissions can only be given to non-admin members of this department.',
        'already_member' => 'This person is already in the department.',
    ],
    'flash' => [
        'created' => 'Department created.',
        'updated' => 'Department updated.',
        'ceiling_saved' => 'Permission ceiling saved.',
        'permissions_saved' => 'Permissions for :name saved.',
        'admin_changed' => 'Admin role for :name updated.',
        'member_removed' => ':name was removed from the department.',
        'invited' => 'Invitation sent to :email.',
        'invitation_revoked' => 'Invitation revoked.',
    ],
];
