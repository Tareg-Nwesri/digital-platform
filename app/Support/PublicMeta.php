<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The `meta` prop of public pages (LLD 09 §4). The root template prints
 * these tags in the first HTML response so crawlers and link previews see
 * them without JavaScript; Vue's <Head> keeps them in sync afterwards.
 */
final class PublicMeta
{
    /**
     * @return array{title: string, description: string, image: string}
     */
    public static function make(?string $title = null, ?string $description = null, ?string $image = null): array
    {
        $brand = (string) __('public_site.brand');

        return [
            'title' => Str::limit(strip_tags((string) (filled($title) ? $title : __('public_site.hero_lead'))), 70).' | '.$brand,
            'description' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($description ?: __('public_site.home_description'))))), 160),
            'image' => $image ?? asset('assets/img/robots/fight/arena-battle.webp'),
        ];
    }

    /**
     * The same page in the other language: public URLs start with the locale.
     *
     * @return array{en: string, ar: string}
     */
    public static function alternates(string $url): array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $rest = preg_replace('#^/(en|ar)(?=/|$)#', '', $path);

        return ['en' => url('/en'.$rest), 'ar' => url('/ar'.$rest)];
    }
}
