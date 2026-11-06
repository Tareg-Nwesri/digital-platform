<?php

use Illuminate\Support\Facades\File;

/*
 * LLD 10 §1: Vue reads the same translations as PHP. i18n:export flattens
 * lang/{locale}/*.php into lang/php_{locale}.json for laravel-vue-i18n.
 */

afterEach(function () {
    File::delete(lang_path('php_xx.json'));
    File::deleteDirectory(lang_path('xx'));
});

it('exports every locale\'s php translations as flat dot-keyed json', function () {
    $this->artisan('i18n:export')->assertSuccessful();

    $arabic = json_decode(File::get(lang_path('php_ar.json')), true);
    $english = json_decode(File::get(lang_path('php_en.json')), true);

    expect($arabic['access.roles.captain'])->toBe('القائد')
        ->and($english['access.roles.captain'])->toBe('Captain')
        ->and($english['teams.errors.limit_reached'])->toContain(':limit');
});

it('skips vendor translations and writes unescaped unicode', function () {
    File::ensureDirectoryExists(lang_path('xx'));
    File::put(lang_path('xx/demo.php'), "<?php return ['nested' => ['key' => 'مرحبا']];");

    $this->artisan('i18n:export')->assertSuccessful();

    $content = File::get(lang_path('php_xx.json'));

    expect($content)->toContain('"demo.nested.key": "مرحبا"')
        ->and(json_decode($content, true))->not->toHaveKey('vendor');
});
