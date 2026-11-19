<?php

use Symfony\Component\Finder\Finder;

/*
 * The "?" page guide: every signed-in page has a tour in
 * resources/js/tours/tours.json, every highlighted element exists in the
 * page markup, and every title and body is written in both languages.
 */

/**
 * Page-name prefixes whose tours are not written yet. Empty it once every
 * page has a tour, so the test then requires all of them.
 */
const TOUR_PAGES_PENDING = ['console/finance/', 'workspace/', 'settings/'];

/**
 * Shared layout targets that only some pages' tours point at.
 */
const TOUR_SHARED_TARGETS = ['finance-nav'];

/**
 * @return array<string, list<array{element?: string, title: string, body: string}>>
 */
function tourDefinitions(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/js/tours/tours.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return list<string> Page names such as console/finance/Payouts
 */
function signedInPages(): array
{
    $pages = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/resources/js/pages')->name('*.vue') as $file) {
        $name = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -4));

        if (str_starts_with($name, 'console/') || str_starts_with($name, 'workspace/') || str_starts_with($name, 'settings/')) {
            $pages[] = $name;
        }
    }

    sort($pages);

    return $pages;
}

/**
 * Every data-tour value in the markup, including (by default) the four a
 * DataTable tour prop adds.
 *
 * @return list<string>
 */
function dataTourTargets(bool $includeTableNames = true): array
{
    $targets = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/resources/js')->name('*.vue')->exclude('tours') as $file) {
        preg_match_all('/data-tour="([a-z0-9-]+)"/', $file->getContents(), $matches);
        array_push($targets, ...$matches[1]);

        if (! $includeTableNames) {
            continue;
        }

        // <DataTable tour="teams"> tags its own search, filters, actions and table.
        preg_match_all('/<DataTable\b[^>]*?\stour="([a-z0-9-]+)"/s', $file->getContents(), $tables);

        foreach ($tables[1] as $name) {
            array_push($targets, "{$name}-search", "{$name}-filters", "{$name}-actions", "{$name}-table");
        }
    }

    return array_values(array_unique($targets));
}

/**
 * @return string|null The translation for "tour.x.y" in a language, or null.
 */
function tourText(string $locale, string $key): ?string
{
    static $groups = [];

    $groups[$locale] ??= require dirname(__DIR__, 2)."/lang/{$locale}/tour.php";
    $value = $groups[$locale];

    foreach (explode('.', substr($key, strlen('tour.'))) as $segment) {
        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            return null;
        }

        $value = $value[$segment];
    }

    return is_string($value) ? $value : null;
}

it('has a tour for every page that should have one, and none for pages that do not exist', function () {
    $tours = tourDefinitions();

    $required = array_filter(
        signedInPages(),
        fn (string $page): bool => ! collect(TOUR_PAGES_PENDING)->contains(fn (string $prefix): bool => str_starts_with($page, $prefix)),
    );

    expect(array_diff(array_keys($tours), signedInPages()))->toBe([])
        ->and(array_values(array_diff($required, array_keys($tours))))->toBe([]);
});

it('gives each tour 3 to 10 steps that start with a centred introduction', function () {
    foreach (tourDefinitions() as $page => $steps) {
        expect(count($steps))->toBeGreaterThanOrEqual(3, "{$page} has too few steps")
            ->and(count($steps))->toBeLessThanOrEqual(10, "{$page} has too many steps")
            ->and($steps[0])->not->toHaveKey('element');

        foreach ($steps as $step) {
            expect($step)->toHaveKeys(['title', 'body']);
        }
    }
});

it('points every step at an element that exists in the page markup', function () {
    $targets = dataTourTargets();
    $missing = [];

    foreach (tourDefinitions() as $page => $steps) {
        foreach ($steps as $step) {
            if (isset($step['element']) && ! in_array($step['element'], $targets, true)) {
                $missing[] = "{$page}: {$step['element']}";
            }
        }
    }

    expect($missing)->toBe([]);
});

it('has no data-tour attributes that no tour uses', function () {
    $used = [];

    foreach (tourDefinitions() as $steps) {
        foreach ($steps as $step) {
            if (isset($step['element'])) {
                $used[] = $step['element'];
            }
        }
    }

    expect(array_values(array_diff(dataTourTargets(false), $used, TOUR_SHARED_TARGETS)))->toBe([]);
});

it('writes every step and the guide buttons in English and Arabic, within length limits', function () {
    $keys = ['tour.ui.button', 'tour.ui.next', 'tour.ui.previous', 'tour.ui.done', 'tour.ui.progress'];

    foreach (tourDefinitions() as $steps) {
        foreach ($steps as $step) {
            array_push($keys, $step['title'], $step['body']);
        }
    }

    $problems = [];

    foreach (array_unique($keys) as $key) {
        $english = tourText('en', $key);
        $arabic = tourText('ar', $key);

        if (blank($english)) {
            $problems[] = "{$key}: missing in English";
        }

        if (blank($arabic) || preg_match('/\p{Arabic}/u', $arabic) !== 1) {
            $problems[] = "{$key}: missing or not Arabic";
        }

        $limit = str_ends_with($key, '.title') ? 40 : 240;

        foreach (['en' => $english, 'ar' => $arabic] as $locale => $text) {
            if ($text !== null && mb_strlen($text) > $limit) {
                $problems[] = "{$key}: {$locale} is ".mb_strlen($text)." characters (limit {$limit})";
            }
        }
    }

    expect($problems)->toBe([]);
});
