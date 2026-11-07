<?php

namespace App\Http\Controllers\Console;

use App\Domain\Catalogue\Models\PartCategory;
use App\Exceptions\DomainRuleViolation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\PartCategoryRequest;
use App\Support\Slugger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 05 §6: the two-level part category tree.
 */
class CatalogueCategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('view-catalogue');

        $categories = PartCategory::query()
            ->whereNull('parent_id')
            ->withCount('parts')
            ->with(['children' => fn ($query) => $query->withCount('parts')])
            ->orderBy('position')
            ->orderBy('name_en')
            ->get()
            ->map(fn (PartCategory $category): array => [
                ...$this->row($category),
                'children' => $category->children->map(fn (PartCategory $child): array => $this->row($child))->all(),
            ])
            ->all();

        return Inertia::render('console/catalogue/Categories', [
            'categories' => $categories,
            'can' => ['manage' => request()->user()->can('manage-catalogue')],
        ]);
    }

    public function store(PartCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        PartCategory::query()->create([
            ...$data,
            'slug' => Slugger::unique(PartCategory::class, $data['name_en']),
            'position' => $data['position'] ?? 0,
        ]);

        return $this->done('category_saved');
    }

    public function update(PartCategoryRequest $request, PartCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return $this->done('category_saved');
    }

    public function destroy(PartCategory $category): RedirectResponse
    {
        Gate::authorize('manage-catalogue');

        if ($category->children()->exists() || $category->parts()->exists()) {
            throw DomainRuleViolation::with(['category' => __('catalogue.errors.category_in_use')]);
        }

        $category->delete();

        return $this->done('category_deleted');
    }

    /**
     * @return array{id: int, slug: string, name: string, nameEn: string, nameAr: string|null, parentId: int|null, position: int, partsCount: int}
     */
    private function row(PartCategory $category): array
    {
        return [
            'id' => $category->getKey(),
            'slug' => $category->slug,
            'name' => (string) $category->localized('name'),
            'nameEn' => $category->name_en,
            'nameAr' => $category->name_ar,
            'parentId' => $category->parent_id,
            'position' => (int) $category->position,
            'partsCount' => (int) $category->parts_count,
        ];
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('catalogue.flash.'.$key)]);

        return back();
    }
}
