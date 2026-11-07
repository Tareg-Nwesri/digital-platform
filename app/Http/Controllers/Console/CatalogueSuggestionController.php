<?php

namespace App\Http\Controllers\Console;

use App\Domain\Catalogue\Models\Part;
use App\Domain\Catalogue\Models\Vendor;
use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Catalogue\Services\SuggestionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ReviewSuggestionRequest;
use App\Support\Money\MoneyFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 05 §6: the queue of team suggestions and its review actions.
 */
class CatalogueSuggestionController extends Controller
{
    /**
     * @var array<string, class-string<Part|Vendor|VendorOffer>>
     */
    private const TYPES = ['part' => Part::class, 'vendor' => Vendor::class, 'offer' => VendorOffer::class];

    public function __construct(private readonly SuggestionService $suggestions) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-catalogue');

        $type = $request->string('type')->toString();
        $types = array_key_exists($type, self::TYPES) ? [$type => self::TYPES[$type]] : self::TYPES;

        $rows = collect($types)
            ->flatMap(fn (string $model) => $model::query()
                ->suggestions()
                ->with(array_merge(['suggestingTeam', 'suggester'], $model === VendorOffer::class ? ['part', 'vendor'] : []))
                ->oldest()
                ->limit(200)
                ->get())
            ->sortBy(fn (Model $item) => $item->created_at)
            ->map(fn (Part|Vendor|VendorOffer $item): array => $this->row($item))
            ->values()
            ->all();

        return Inertia::render('console/catalogue/Suggestions', [
            'suggestions' => $rows,
            'type' => $type !== '' && array_key_exists($type, self::TYPES) ? $type : null,
            'mergeTargets' => fn (): array => [
                'part' => Part::query()->approved()->orderBy('name')->get(['id', 'name'])->map(fn (Part $part): array => ['id' => $part->getKey(), 'name' => $part->name])->all(),
                'vendor' => Vendor::query()->approved()->orderBy('name')->get(['id', 'name'])->map(fn (Vendor $vendor): array => ['id' => $vendor->getKey(), 'name' => $vendor->name])->all(),
            ],
            'can' => ['review' => $request->user()->can('review-catalogue')],
        ]);
    }

    public function approve(ReviewSuggestionRequest $request, string $type, int $id): RedirectResponse
    {
        $this->suggestions->approve($this->resolve($type, $id), $request->validated('edits'), $request->user());

        return $this->done('approved');
    }

    public function reject(ReviewSuggestionRequest $request, string $type, int $id): RedirectResponse
    {
        $this->suggestions->reject($this->resolve($type, $id), $request->validated('note'), $request->user());

        return $this->done('rejected');
    }

    public function merge(ReviewSuggestionRequest $request, string $type, int $id): RedirectResponse
    {
        $item = $this->resolve($type, $id);
        abort_if($item instanceof VendorOffer, 404);

        $into = $item::query()->findOrFail($request->integer('into_id'));
        $this->suggestions->merge($item, $into, $request->user());

        return $this->done('merged');
    }

    private function resolve(string $type, int $id): Part|Vendor|VendorOffer
    {
        $model = self::TYPES[$type] ?? abort(404);

        return $model::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Part|Vendor|VendorOffer $item): array
    {
        $base = [
            'key' => $item::catalogueType().'-'.$item->getKey(),
            'id' => $item->getKey(),
            'type' => $item::catalogueType(),
            'team' => $item->suggestingTeam?->name,
            'suggestedBy' => $item->suggester?->name,
            'createdAt' => $item->created_at?->toIso8601String(),
            'alsoApproves' => $this->suggestions->cascadeFor($item)
                ->map(fn (Part|Vendor $related): array => ['type' => $related::catalogueType(), 'name' => $related->name])
                ->all(),
        ];

        return match (true) {
            $item instanceof Part => [...$base, 'name' => $item->name, 'details' => array_filter([$item->part_number, $item->description_en]), 'fields' => [
                'name' => $item->name, 'part_number' => $item->part_number, 'category_id' => $item->category_id, 'description_en' => $item->description_en,
            ]],
            $item instanceof Vendor => [...$base, 'name' => $item->name, 'details' => array_filter([$item->city, $item->website, $item->phone]), 'fields' => [
                'name' => $item->name, 'city' => $item->city, 'website' => $item->website, 'phone' => $item->phone,
            ]],
            default => [...$base, 'name' => $item->part->name.' · '.$item->vendor->name, 'details' => array_filter([MoneyFormatter::format($item->price), $item->url]), 'fields' => [
                'price' => (string) $item->price->getAmount(), 'currency' => $item->currency, 'url' => $item->url, 'in_stock' => $item->in_stock,
            ]],
        };
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalogue.flash.'.$key)]);

        return back();
    }
}
