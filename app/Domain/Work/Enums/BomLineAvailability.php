<?php

namespace App\Domain\Work\Enums;

/**
 * Computed for each BOM line, never stored (LLD 04 §2).
 */
enum BomLineAvailability: string
{
    case Available = 'available';
    case PartArchived = 'part_archived';
    case OfferRemoved = 'offer_removed';
    case OfferOutOfStock = 'offer_out_of_stock';
    case NoOffer = 'no_offer';
}
