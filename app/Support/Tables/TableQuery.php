<?php

namespace App\Support\Tables;

use App\Support\LiteralLike;
use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Server-side search, sorting, filters and pagination for list pages (LLD 10 §5).
 * The query string is the table state: q, sort, direction, filter[...], page, per_page.
 *
 * TableQuery::for(Team::query(), $request)
 *     ->searchable(['name', 'location'])
 *     ->sortable(['name', 'created_at'], default: 'name')
 *     ->filters(['status' => fn ($q, $value) => $q->where('status', $value)])
 *     ->paginate(fn (Team $team) => [...]);
 */
final class TableQuery
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /**
     * @var list<string>
     */
    private array $searchable = [];

    /**
     * @var list<string>
     */
    private array $sortable = [];

    private ?string $defaultSort = null;

    private string $defaultDirection = 'asc';

    /**
     * @var array<string, Closure(Builder, mixed): mixed>
     */
    private array $filters = [];

    private function __construct(
        private readonly Builder $query,
        private readonly Request $request,
    ) {}

    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /**
     * @param  list<string>  $columns
     */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function sortable(array $columns, ?string $default = null, string $direction = 'asc'): self
    {
        $this->sortable = $columns;
        $this->defaultSort = $default;
        $this->defaultDirection = $direction === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    /**
     * @param  array<string, Closure(Builder, mixed): mixed>  $filters
     */
    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @param  (Closure(Model): array<string, mixed>)|null  $transform
     * @return array{data: list<mixed>, meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}, query: array{q: string, sort: string|null, direction: string, per_page: int, filter: array<string, mixed>}}
     */
    public function paginate(?Closure $transform = null): array
    {
        $state = $this->state();
        $query = $this->query;

        if ($state['q'] !== '' && $this->searchable !== []) {
            $query->where(function (Builder $matches) use ($state): void {
                foreach ($this->searchable as $index => $column) {
                    LiteralLike::where($matches, $column, $state['q'], $index === 0 ? 'and' : 'or');
                }
            });
        }

        foreach ($state['filter'] as $name => $value) {
            ($this->filters[$name])($query, $value);
        }

        if ($state['sort'] !== null) {
            $query->orderBy($state['sort'], $state['direction']);
        }

        $paginator = $query->orderBy($query->getModel()->getQualifiedKeyName())
            ->paginate($state['per_page'], ['*'], 'page', max(1, (int) $this->request->query('page', 1)))
            ->appends($this->request->query());

        $items = $paginator->getCollection();

        return [
            'data' => ($transform !== null ? $items->map($transform) : $items)->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'query' => $state,
        ];
    }

    /**
     * The validated table state from the query string.
     *
     * @return array{q: string, sort: string|null, direction: string, per_page: int, filter: array<string, mixed>}
     */
    public function state(): array
    {
        $sort = (string) $this->request->query('sort', '');
        $direction = strtolower((string) $this->request->query('direction', $this->defaultDirection));
        $filters = $this->request->query('filter', []);

        return [
            'q' => mb_substr(trim((string) $this->request->query('q', '')), 0, 100),
            'sort' => in_array($sort, $this->sortable, true) ? $sort : $this->defaultSort,
            'direction' => $direction === 'desc' ? 'desc' : 'asc',
            'per_page' => max(1, min(self::MAX_PER_PAGE, (int) $this->request->query('per_page', self::DEFAULT_PER_PAGE))),
            'filter' => is_array($filters)
                ? array_filter(
                    array_intersect_key($filters, $this->filters),
                    fn (mixed $value): bool => $value !== null && $value !== '',
                )
                : [],
        ];
    }
}
