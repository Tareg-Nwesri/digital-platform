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

/**
 * Every Arabic string Vue can show: lang/ar.json plus the PHP groups that
 * i18n:export flattens into dot keys (LLD 10 §1).
 *
 * @return array<string, string>
 */
function arabicTranslations(): array
{
    $lang = dirname(__DIR__, 2).'/lang';
    $translations = json_decode((string) file_get_contents("{$lang}/ar.json"), true);

    $flatten = function (array $items, string $prefix) use (&$flatten, &$translations): void {
        foreach ($items as $key => $value) {
            is_array($value) ? $flatten($value, "{$prefix}{$key}.") : $translations["{$prefix}{$key}"] = $value;
        }
    };

    foreach (glob("{$lang}/ar/*.php") as $group) {
        $flatten(require $group, basename($group, '.php').'.');
    }

    return $translations;
}

it('has an Arabic translation for every key the frontend translates', function () {
    $files = Finder::create()
        ->files()
        ->in(dirname(__DIR__, 2).'/resources/js')
        ->name(['*.vue', '*.ts'])
        ->exclude(['actions', 'routes', 'wayfinder']);

    $arabic = arabicTranslations();
    $missing = [];

    foreach ($files as $file) {
        preg_match_all('/(?:\$t|\$tChoice|\btrans|\btransChoice|\bwTrans)\(\s*([\'"])((?:(?!\1).)+)\1/', $file->getContents(), $matches);

        foreach ($matches[2] as $key) {
            if (! array_key_exists($key, $arabic)) {
                $missing[] = "{$key} ({$file->getRelativePathname()})";
            }
        }
    }

    expect($missing)->toBe([]);
});

it('uses DatePicker instead of native date and time inputs', function () {
    $offenders = array_keys(array_filter(
        vueSources(),
        fn (string $source): bool => preg_match('/type="(date|datetime-local|time|month|week)"/', $source) === 1,
    ));

    expect($offenders)->toBe([]);
});
