<?php

/*
 * Hostinger keeps the whole project in public_html. The root .htaccess must
 * route into public/ and hide dotfiles; both .htaccess files must select
 * PHP 8.5, or the site falls back to 8.3 and Composer's platform check
 * stops every request.
 */

function deploymentFile(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

it('runs PHP 8.5 from both .htaccess files', function (string $path) {
    expect(deploymentFile($path))->toContain('AddHandler application/x-httpd-alt-php85 .php');
})->with(['.htaccess', 'public/.htaccess']);

it('routes the project root into public and blocks dotfiles', function () {
    $root = deploymentFile('.htaccess');

    expect($root)
        ->toContain('Options -Indexes')
        ->toContain('RewriteRule ^\.well-known/ - [L]')
        ->toContain('RewriteRule (^|/)\. - [F,L]')
        ->toContain('RewriteRule ^(.*)$ public/$1 [L]')
        ->and(strpos($root, '.well-known'))->toBeLessThan(strpos($root, '[F,L]'));
});

it('never exports COMPOSER from the server update script', function () {
    $script = deploymentFile('deploy/server-update.sh');

    expect($script)
        ->toContain('COMPOSER_BIN="${COMPOSER_BIN:-composer}"')
        ->not->toMatch('/^\s*COMPOSER=/m');
});
