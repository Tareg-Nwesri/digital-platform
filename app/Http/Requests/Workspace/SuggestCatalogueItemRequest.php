<?php

namespace App\Http\Requests\Workspace;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Http\Requests\Console\PartRequest;
use App\Http\Requests\Console\SyncOffersRequest;
use App\Http\Requests\Console\VendorRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A team suggesting a part, a vendor or an offer: the same fields as the
 * console, chosen by the route.
 */
class SuggestCatalogueItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team $team */
        $team = $this->route('team');
        app(TeamFeatures::class)->assertActive($team, TeamFeature::PartsSuggest);

        return $this->user()->can('catalogue.suggest');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match (true) {
            $this->routeIs('workspace.catalogue.vendors.store') => VendorRequest::fieldRules(),
            $this->routeIs('workspace.catalogue.offers.store') => [
                'part_id' => ['required', 'integer'],
                ...SyncOffersRequest::fieldRules(),
            ],
            default => PartRequest::fieldRules(),
        };
    }
}
