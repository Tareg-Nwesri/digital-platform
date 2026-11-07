<?php

return [
    'status' => [
        'draft' => 'مسودة',
        'open' => 'مفتوحة للتسجيل',
        'closed' => 'مغلقة للتسجيل',
        'running' => 'جارية',
        'finished' => 'منتهية',
        'archived' => 'مؤرشفة',
    ],
    'visibility' => [
        'public' => 'عامة',
        'invite_only' => 'بالدعوة فقط',
    ],
    'registration_status' => [
        'invited' => 'مدعو',
        'pending' => 'قيد المراجعة',
        'approved' => 'مقبول',
        'waitlisted' => 'قائمة الانتظار',
        'rejected' => 'مرفوض',
        'withdrawn' => 'منسحب',
    ],
    'rules' => [
        'team_inactive' => 'الفريق غير نشط.',
        'feature_off' => 'مستوى هذا الفريق لا يشمل التسجيل في الفعاليات.',
        'not_visible' => 'هذه الفعالية بالدعوة فقط.',
        'window_closed' => 'التسجيل في هذه الفعالية غير مفتوح.',
        'event_closed' => 'لا يمكن إضافة الفرق إلا والفعالية مفتوحة أو مغلقة للتسجيل أو جارية.',
        'duplicate' => 'للفريق مشاركة قائمة في هذه الفئة.',
        'members_count' => 'تتطلب هذه الفئة من :min إلى :max أعضاء.',
        'member_not_in_team' => 'ليسوا من أعضاء الفريق: :names.',
        'member_no_birthdate' => 'تاريخ الميلاد غير مسجل لـ: :names.',
        'member_age' => 'خارج الفئة العمرية :min–:max يوم الفعالية: :names.',
    ],
    'errors' => [
        'withdraw_closed' => 'أُغلق التسجيل، ولا يمكن سحب هذه المشاركة الآن إلا من المنظمين.',
        'still_full' => 'الفعالية أو الفئة ما زالت ممتلئة.',
        'category_mismatch' => 'هذه الفئة تابعة لفعالية أخرى.',
        'not_invited' => 'هذه المشاركة لا تنتظر قبول الفريق.',
        'needs_category' => 'أضف فئة واحدة على الأقل قبل فتح الفعالية.',
        'category_has_entries' => 'لهذه الفئات مشاركات ولا يمكن حذفها: :names.',
        'results_not_finished' => 'يمكن تسجيل النتائج بعد انتهاء الفعالية.',
        'result_not_approved' => 'تُسجَّل النتائج للمشاركات المقبولة في هذه الفعالية فقط.',
        'delete_not_draft' => 'لا يمكن حذف إلا الفعاليات المسودة.',
    ],
];
