<?php

namespace App\Http\Controllers\Console;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Services\CatalogueService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\VendorRequest;
use App\Support\Tables\TableQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 05 §6: vendors in the console.
 */
class CatalogueVendorController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-catalogue');

        $vendors = TableQuery::for(Vendor::query()->with('suggestingTeam')->withCount(['offers' => fn ($query) => $query->where('status', CatalogueStatus::Approved)]), $request)
            ->searchable(['name', 'city', 'email', 'phone'])
            ->sortable(['name', 'city', 'created_at'], default: 'name')
            ->filters([
                'status' => fn (Builder $query, mixed $value) => $query->where('status', (string) $value),
            ])
            ->paginate(fn (Vendor $vendor): array => [
                'id' => $vendor->getKey(),
                'name' => $vendor->name,
                'website' => $vendor->website,
                'email' => $vendor->email,
                'phone' => $vendor->phone,
                'city' => $vendor->city,
                'address' => $vendor->address,
                'latitude' => $vendor->latitude,
                'longitude' => $vendor->longitude,
                'status' => $vendor->status->value,
                'team' => $vendor->suggestingTeam?->name,
                'offers' => (int) $vendor->offers_count,
            ]);

        return Inertia::render('console/catalogue/Vendors', [
            'vendors' => $vendors,
            'statuses' => array_column(CatalogueStatus::cases(), 'value'),
            'can' => ['manage' => $request->user()->can('manage-catalogue')],
        ]);
    }

    public function store(VendorRequest $request): RedirectResponse
    {
        $this->catalogue->createVendor($request->validated(), $request->user());

        return $this->done('vendor_saved');
    }

    public function update(VendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->catalogue->updateVendor($vendor, $request->validated(), $request->user());

        return $this->done('vendor_saved');
    }

    public function destroy(Request $request, Vendor $vendor): RedirectResponse
    {
        Gate::authorize('manage-catalogue');

        $this->catalogue->archiveVendor($vendor, $request->user());

        return $this->done('vendor_archived');
    }

    public function restore(Request $request, Vendor $vendor): RedirectResponse
    {
        Gate::authorize('manage-catalogue');

        $this->catalogue->restoreVendor($vendor, $request->user());

        return $this->done('vendor_restored');
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalogue.flash.'.$key)]);

        return back();
    }
}
