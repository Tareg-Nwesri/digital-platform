<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Models\User;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Staff curation of the catalogue (LLD 05 §4). Parts and vendors are
 * archived, never deleted, so bill-of-materials lines stay valid.
 */
final class CatalogueService
{
    public const PART_FIELDS = ['category_id', 'name', 'part_number', 'description_en', 'description_ar', 'specs'];

    public const VENDOR_FIELDS = ['name', 'website', 'email', 'phone', 'city', 'address', 'latitude', 'longitude'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPart(array $data, ?User $by = null): Part
    {
        $part = Part::query()->create([...$this->only($data, self::PART_FIELDS), 'status' => CatalogueStatus::Approved]);
        $this->log($part, $by, 'catalogue.created');

        return $part;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePart(Part $part, array $data, ?User $by = null): Part
    {
        $part->update($this->only($data, self::PART_FIELDS));
        $this->log($part, $by, 'catalogue.updated');

        return $part;
    }

    public function archivePart(Part $part, ?User $by = null): void
    {
        $this->setStatus($part, CatalogueStatus::Archived, $by);
    }

    public function restorePart(Part $part, ?User $by = null): void
    {
        $this->setStatus($part, CatalogueStatus::Approved, $by);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createVendor(array $data, ?User $by = null): Vendor
    {
        $vendor = Vendor::query()->create([...$this->only($data, self::VENDOR_FIELDS), 'status' => CatalogueStatus::Approved]);
        $this->log($vendor, $by, 'catalogue.created');

        return $vendor;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateVendor(Vendor $vendor, array $data, ?User $by = null): Vendor
    {
        $vendor->update($this->only($data, self::VENDOR_FIELDS));
        $this->log($vendor, $by, 'catalogue.updated');

        return $vendor;
    }

    public function archiveVendor(Vendor $vendor, ?User $by = null): void
    {
        $this->setStatus($vendor, CatalogueStatus::Archived, $by);
    }

    public function restoreVendor(Vendor $vendor, ?User $by = null): void
    {
        $this->setStatus($vendor, CatalogueStatus::Approved, $by);
    }

    /**
     * Create or update the approved offer for (part, vendor, currency).
     *
     * @param  array{vendor_id: int, price: string|int|float, currency?: string, url?: string|null, in_stock?: bool, checked_at?: string|null}  $data
     */
    public function upsertOffer(Part $part, array $data, ?User $by = null): VendorOffer
    {
        return DB::transaction(function () use ($part, $data, $by): VendorOffer {
            $attributes = self::offerAttributes($data);

            $offer = VendorOffer::query()
                ->where('part_id', $part->getKey())
                ->where('vendor_id', $attributes['vendor_id'])
                ->where('currency', $attributes['currency'])
                ->where('status', CatalogueStatus::Approved)
                ->lockForUpdate()
                ->first();

            if ($offer === null) {
                $offer = $part->offers()->create([...$attributes, 'status' => CatalogueStatus::Approved]);
            } else {
                $offer->update($attributes);
            }

            $this->log($offer, $by, 'catalogue.offer_saved');

            return $offer;
        });
    }

    /**
     * Offers no bill of materials uses are deleted. Once BOM lines exist
     * (phase 3) an offer in use is archived instead.
     */
    public function removeOffer(VendorOffer $offer, ?User $by = null): void
    {
        $this->log($offer, $by, 'catalogue.offer_removed');
        $offer->delete();
    }

    /**
     * Turn request data into stored offer columns: the price becomes minor
     * units of its currency (3 decimals for LYD).
     *
     * @param  array<string, mixed>  $data
     * @return array{vendor_id: int, price_minor: int, currency: string, url: string|null, in_stock: bool, checked_at: string|null}
     */
    public static function offerAttributes(array $data): array
    {
        $currency = (string) ($data['currency'] ?? 'LYD');
        $money = Money::of((string) $data['price'], $currency, roundingMode: RoundingMode::HalfUp);

        return [
            'vendor_id' => (int) $data['vendor_id'],
            'price_minor' => $money->getMinorAmount()->toInt(),
            'currency' => $currency,
            'url' => $data['url'] ?? null,
            'in_stock' => (bool) ($data['in_stock'] ?? true),
            'checked_at' => $data['checked_at'] ?? null,
        ];
    }

    private function setStatus(Part|Vendor $item, CatalogueStatus $status, ?User $by): void
    {
        $item->forceFill(['status' => $status])->save();
        $this->log($item, $by, $status === CatalogueStatus::Archived ? 'catalogue.archived' : 'catalogue.restored');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function only(array $data, array $fields): array
    {
        return array_intersect_key($data, array_flip($fields));
    }

    private function log(Model $item, ?User $by, string $event): void
    {
        activity('catalogue')->performedOn($item)->causedBy($by)->event($event)
            ->log(ucfirst(str_replace(['catalogue.', '_'], ['', ' '], $event)));
    }
}
