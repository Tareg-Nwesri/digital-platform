<?php

return [
    'status' => [
        'approved' => 'Approved',
        'suggested' => 'Suggested',
        'rejected' => 'Rejected',
        'archived' => 'Archived',
    ],
    'types' => [
        'part' => 'Part',
        'vendor' => 'Vendor',
        'offer' => 'Offer',
    ],
    'merged_note' => 'Merged into :name.',
    'errors' => [
        'not_visible' => 'Choose an approved part and vendor, or your team’s own suggestions.',
        'merge_target' => 'Merge into a different, approved item of the same type.',
        'team_inactive' => 'Only active teams can suggest catalogue items.',
        'not_suggested' => 'This item has already been reviewed.',
        'category_depth' => 'Categories can only be two levels deep.',
        'category_in_use' => 'Move this category’s parts and subcategories first.',
    ],
    'flash' => [
        'part_saved' => 'The part was saved.',
        'part_archived' => 'The part was archived. It stays in existing parts lists.',
        'part_restored' => 'The part is back in the catalogue.',
        'vendor_saved' => 'The vendor was saved.',
        'vendor_archived' => 'The vendor was archived.',
        'vendor_restored' => 'The vendor is back in the catalogue.',
        'offers_saved' => 'Offers saved.',
        'category_saved' => 'The category was saved.',
        'category_deleted' => 'The category was deleted.',
        'approved' => 'The suggestion was approved.',
        'rejected' => 'The suggestion was rejected.',
        'merged' => 'The suggestion was merged.',
    ],
];
