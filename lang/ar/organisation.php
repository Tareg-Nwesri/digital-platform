<?php

return [
    'errors' => [
        'super_admin_only' => 'المشرف العام فقط يمكنه تغيير سقف صلاحيات القسم.',
        'not_delegable' => 'لا يمكن تفويض الصلاحية ":permission".',
        'outside_ceiling' => 'الصلاحية ":permission" خارج سقف صلاحيات هذا القسم.',
        'admin_only' => 'مشرف هذا القسم فقط يمكنه تغيير صلاحيات الأعضاء.',
        'member_only' => 'تمنح الصلاحيات فقط لأعضاء القسم من غير المشرفين.',
        'already_member' => 'هذا الشخص عضو في القسم بالفعل.',
    ],
    'flash' => [
        'created' => 'تم إنشاء القسم.',
        'updated' => 'تم تحديث القسم.',
        'ceiling_saved' => 'تم حفظ سقف الصلاحيات.',
        'permissions_saved' => 'تم حفظ صلاحيات :name.',
        'admin_changed' => 'تم تحديث دور المشرف لـ :name.',
        'member_removed' => 'تمت إزالة :name من القسم.',
        'invited' => 'تم إرسال الدعوة إلى :email.',
        'invitation_revoked' => 'تم إلغاء الدعوة.',
    ],
];
