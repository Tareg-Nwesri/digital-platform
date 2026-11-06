<?php

use App\Models\User;
use App\Support\Tables\TableQuery;
use Illuminate\Http\Request;

/*
 * LLD 10 §5 / HLD 10.3: every list is searched, sorted, filtered and paginated
 * on the server with the state in the URL, and returns one shape for DataTable.vue.
 */

beforeEach(function () {
    User::factory()->create(['name_en' => 'Amina Salem', 'email' => 'amina@example.com', 'locale' => 'ar']);
    User::factory()->create(['name_en' => 'Bashir Omar', 'email' => 'bashir@example.com', 'locale' => 'en']);
    User::factory()->create(['name_en' => 'Cyrine 50% Off', 'email' => 'cyrine@example.com', 'locale' => 'en']);
});

function table(array $query): TableQuery
{
    return TableQuery::for(User::query(), Request::create('/list', 'GET', $query))
        ->searchable(['name_en', 'email'])
        ->sortable(['name_en', 'created_at'], default: 'name_en')
        ->filters(['locale' => fn ($builder, $value) => $builder->where('locale', $value)]);
}

it('sorts by an allowed column in either direction and defaults otherwise', function () {
    expect(collect(table(['sort' => 'name_en', 'direction' => 'desc'])->paginate()['data'])->pluck('name_en')->first())
        ->toBe('Cyrine 50% Off')
        ->and(collect(table(['sort' => 'password'])->paginate()['data'])->pluck('name_en')->first())
        ->toBe('Amina Salem');
});

it('searches the searchable columns literally', function () {
    expect(table(['q' => 'bashir'])->paginate()['meta']['total'])->toBe(1)
        ->and(table(['q' => '50%'])->paginate()['meta']['total'])->toBe(1)
        ->and(table(['q' => '%'])->paginate()['meta']['total'])->toBe(1);
});

it('applies only declared filters', function () {
    expect(table(['filter' => ['locale' => 'ar']])->paginate()['meta']['total'])->toBe(1)
        ->and(table(['filter' => ['email' => 'x']])->paginate()['meta']['total'])->toBe(3);
});

it('bounds the page size and returns the standard shape for the table', function () {
    $result = table(['per_page' => 2, 'page' => 2])->paginate();

    expect($result['meta'])->toMatchArray(['current_page' => 2, 'last_page' => 2, 'per_page' => 2, 'total' => 3])
        ->and($result['data'])->toHaveCount(1)
        ->and($result['query'])->toMatchArray(['sort' => 'name_en', 'direction' => 'asc', 'per_page' => 2])
        ->and(table(['per_page' => 5000])->paginate()['meta']['per_page'])->toBe(100);
});

it('maps rows through a transformer', function () {
    $result = table([])->paginate(fn (User $user): array => ['id' => $user->id, 'label' => strtoupper($user->name_en)]);

    expect($result['data'][0])->toBe(['id' => User::query()->where('name_en', 'Amina Salem')->value('id'), 'label' => 'AMINA SALEM']);
});
