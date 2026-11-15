<?php

use Symfony\Component\Finder\Finder;

/*
 * The UI uses the app's own form controls everywhere: FormSelect/FormOption
 * (or SearchSelect) instead of native <select>, and DatePicker instead of
 * native date and time inputs. The shared components themselves are exempt.
 */

/**
 * @return array<string, string>
 */
function vueSources(): array
{
    $files = Finder::create()
        ->files()
        ->in(dirname(__DIR__, 2).'/resources/js')
        ->name('*.vue')
        ->exclude(['components/ui', 'components/forms']);

    $sources = [];

    foreach ($files as $file) {
        $sources[$file->getRelativePathname()] = $file->getContents();
    }

    return $sources;
}

it('uses FormSelect instead of native select boxes', function () {
    $offenders = array_keys(array_filter(vueSources(), fn (string $source): bool => preg_match('/<select\b/', $source) === 1));

    expect($offenders)->toBe([]);
});

it('uses DatePicker instead of native date and time inputs', function () {
    $offenders = array_keys(array_filter(
        vueSources(),
        fn (string $source): bool => preg_match('/type="(date|datetime-local|time|month|week)"/', $source) === 1,
    ));

    expect($offenders)->toBe([]);
});
