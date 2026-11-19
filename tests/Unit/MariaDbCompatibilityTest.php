<?php

use Symfony\Component\Finder\Finder;

/*
 * Development and tests run on MySQL, production (Hostinger) on MariaDB.
 * Raw SQL must use what both accept: these MySQL-only forms pass every
 * test here and fail only in production.
 */

it('keeps raw SQL to what MariaDB also accepts', function (string $pattern) {
    $files = Finder::create()
        ->files()
        ->in(array_map(fn (string $folder): string => dirname(__DIR__, 2).'/'.$folder, ['app', 'database', 'routes']))
        ->name('*.php');

    $offenders = [];

    foreach ($files as $file) {
        foreach (explode("\n", $file->getContents()) as $number => $line) {
            if (preg_match($pattern, $line) === 1) {
                $offenders[] = $file->getRelativePathname().':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
})->with([
    'CAST(... AS JSON): MariaDB has no JSON type' => '/\bCAST\s*\(.*\bAS\s+JSON\b/i',
    'the ->> and -> column operators' => '/[\'"][^\'"]*\w\s*->>?\s*[\'"$]/',
    'MEMBER OF()' => '/\bMEMBER\s+OF\s*\(/i',
    'ANY_VALUE()' => '/\bANY_VALUE\s*\(/i',
    'REGEXP_LIKE()' => '/\bREGEXP_LIKE\s*\(/i',
    'UUID_TO_BIN() and BIN_TO_UUID()' => '/\b(UUID_TO_BIN|BIN_TO_UUID)\s*\(/i',
    'lateral joins' => '/\b(join|leftJoin)Lateral\s*\(/',
]);
