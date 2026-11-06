<?php

return [
    'roles' => [
        'captain' => 'القائد',
        'coach' => 'المدرب',
        'member' => 'عضو',
        'viewer' => 'مشاهد',
        'department-admin' => 'مشرف القسم',
        'department-member' => 'عضو القسم',
    ],
    'invitations' => [
        'invalid_role' => 'لا يمكن استخدام هذا الدور في مساحة العمل هذه.',
        'already_member' => 'هذا الشخص عضو بالفعل.',
        'not_pending' => 'هذه الدعوة لم تعد صالحة.',
        'invalid' => 'رابط الدعوة غير صالح أو منتهي الصلاحية.',
        'password_to_confirm' => 'أدخل كلمة المرور الحالية لقبول الدعوة.',
        'accepted' => 'مرحبًا! تم قبول دعوتك.',
        'mail' => [
            'subject' => 'دعوة للانضمام إلى :name',
            'greeting' => 'مرحبًا!',
            'intro' => 'تمت دعوتك للانضمام إلى :name بصفة :role في منصة الروبوتات الرقمية.',
            'action' => 'قبول الدعوة',
            'expires' => 'تنتهي صلاحية هذه الدعوة في :date.',
        ],
    ],
];
