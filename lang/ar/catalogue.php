<?php

return [
    'status' => [
        'approved' => 'معتمد',
        'suggested' => 'مقترح',
        'rejected' => 'مرفوض',
        'archived' => 'مؤرشف',
    ],
    'types' => [
        'part' => 'قطعة',
        'vendor' => 'مورّد',
        'offer' => 'عرض سعر',
    ],
    'merged_note' => 'دُمج في :name.',
    'errors' => [
        'not_visible' => 'اختر قطعة ومورّدًا معتمدين، أو من اقتراحات فريقك.',
        'merge_target' => 'ادمج في عنصر آخر معتمد من النوع نفسه.',
        'team_inactive' => 'لا يقترح عناصر الكتالوج إلا الفرق النشطة.',
        'not_suggested' => 'تمت مراجعة هذا العنصر بالفعل.',
        'category_depth' => 'لا يمكن أن تتجاوز الفئات مستويين.',
        'category_in_use' => 'انقل قطع هذه الفئة وفئاتها الفرعية أولًا.',
    ],
    'flash' => [
        'suggested' => 'شكراً! سيراجع المنظمون اقتراحك.',
        'part_saved' => 'تم حفظ القطعة.',
        'part_archived' => 'أُرشفت القطعة، وتبقى في قوائم القطع الحالية.',
        'part_restored' => 'عادت القطعة إلى الكتالوج.',
        'vendor_saved' => 'تم حفظ المورّد.',
        'vendor_archived' => 'أُرشف المورّد.',
        'vendor_restored' => 'عاد المورّد إلى الكتالوج.',
        'offers_saved' => 'تم حفظ العروض.',
        'category_saved' => 'تم حفظ الفئة.',
        'category_deleted' => 'تم حذف الفئة.',
        'approved' => 'اعتُمد الاقتراح.',
        'rejected' => 'رُفض الاقتراح.',
        'merged' => 'دُمج الاقتراح.',
    ],
];
