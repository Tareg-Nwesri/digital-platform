<?php

return [
    'post_status' => [
        'draft' => 'مسودة',
        'in_review' => 'قيد المراجعة',
        'scheduled' => 'مجدول',
        'published' => 'منشور',
        'archived' => 'مؤرشف',
    ],
    'message_status' => [
        'new' => 'جديدة',
        'in_progress' => 'قيد المتابعة',
        'closed' => 'مغلقة',
    ],
    'errors' => [
        'archived' => 'لا يمكن تعديل المنشورات المؤرشفة.',
        'not_draft' => 'لا تُرسل للمراجعة إلا المسودات.',
        'cannot_publish' => 'لا يمكن نشر هذا المنشور من حالته الحالية.',
        'department_not_allowed' => 'اختر قسمًا يمكنك فيه تحرير الأخبار.',
        'assignee_cannot_view' => 'أسند الرسالة إلى من يمكنه قراءة بريد هذا القسم.',
    ],
    'flash' => [
        'created' => 'أُنشئت المسودة.',
        'saved' => 'تم حفظ المنشور.',
        'submitted' => 'أُرسل للمراجعة.',
        'published' => 'نُشر المنشور.',
        'scheduled' => 'جُدول المنشور.',
        'unpublished' => 'أُعيد المنشور إلى المسودات.',
        'removed' => 'أُزيل المنشور.',
        'updated' => 'تم تحديث الرسالة.',
        'note_added' => 'أُضيفت الملاحظة.',
        'settings_saved' => 'تم حفظ الإعدادات.',
    ],
    'mail' => [
        'subject' => 'رسالة جديدة: :subject',
        'intro' => 'كتب :name عبر الموقع:',
        'action' => 'فتح صندوق الرسائل',
    ],
];
