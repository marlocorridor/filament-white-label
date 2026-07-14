<?php

namespace MuazzamBuilds\FilamentWhiteLabel\Support;

use Illuminate\Support\HtmlString;

final class FontOptions
{
    /**
     * Curated Bunny Fonts families for the settings select.
     *
     * @return list<string>
     */
    public static function families(): array
    {
        return [
            'Inter',
            'Figtree',
            'DM Sans',
            'Plus Jakarta Sans',
            'Outfit',
            'Manrope',
            'Nunito Sans',
            'Source Sans 3',
            'IBM Plex Sans',
            'Roboto',
            'Open Sans',
            'Lato',
            'Montserrat',
            'Poppins',
            'Raleway',
            'Work Sans',
            'Space Grotesk',
            'Sora',
            'Geist',
            'Public Sans',
            'Merriweather',
            'Lora',
            'Playfair Display',
            'Source Serif 4',
            'JetBrains Mono',
            'Fira Code',
        ];
    }

    /**
     * Plain labels (value => label) without HTML.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::families())
            ->mapWithKeys(fn (string $font): array => [$font => $font])
            ->all();
    }

    /**
     * Option labels styled with each font for live preview in the select.
     *
     * @return array<string, string>
     */
    public static function previewOptions(): array
    {
        return collect(self::families())
            ->mapWithKeys(fn (string $font): array => [
                $font => self::previewLabel($font),
            ])
            ->all();
    }

    public static function previewLabel(string $font): string
    {
        $escaped = e($font);
        $family = str_replace("'", "\\'", $font);

        return '<span style="font-family: \'' . $family . '\', ui-sans-serif, system-ui, sans-serif; font-size: 0.95rem;">'
            . $escaped
            . '</span>';
    }

    public static function bunnySlug(string $font): string
    {
        return (string) str($font)->replace(' ', '-')->lower()->kebab();
    }

    /**
     * Single Bunny Fonts stylesheet covering every preview family.
     */
    public static function previewStylesheetUrl(): string
    {
        $families = collect(self::families())
            ->map(fn (string $font): string => self::bunnySlug($font) . ':400,500,600,700')
            ->implode('|');

        return 'https://fonts.bunny.net/css?family=' . $families . '&display=swap';
    }

    public static function previewStylesheetHtml(): HtmlString
    {
        $url = e(self::previewStylesheetUrl());

        return new HtmlString(
            '<link rel="preconnect" href="https://fonts.bunny.net">'
            . '<link href="' . $url . '" rel="stylesheet" data-filament-white-label-font-preview>'
        );
    }
}
