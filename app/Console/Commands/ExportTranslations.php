<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Flattens lang/{locale}/*.php into lang/php_{locale}.json so Vue
 * (laravel-vue-i18n) uses the same strings as PHP. Run before every build.
 */
#[Signature('i18n:export')]
#[Description('Export PHP translation files to JSON for the Vue front end')]
class ExportTranslations extends Command
{
    public function handle(): int
    {
        $locales = collect(File::directories(lang_path()))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $locale): bool => $locale === 'vendor');

        foreach ($locales as $locale) {
            $messages = [];

            foreach (File::files(lang_path($locale)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $group = $file->getFilenameWithoutExtension();
                $values = require $file->getPathname();

                if (is_array($values)) {
                    foreach (Arr::dot($values) as $key => $value) {
                        if (is_string($value)) {
                            $messages[$group.'.'.$key] = $value;
                        }
                    }
                }
            }

            ksort($messages);

            File::put(
                lang_path("php_{$locale}.json"),
                json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
            );

            $this->components->info(sprintf('%s: %d strings', $locale, count($messages)));
        }

        return self::SUCCESS;
    }
}
