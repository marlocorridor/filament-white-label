<?php

namespace MuazzamBuilds\FilamentWhiteLabel;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use MuazzamBuilds\FilamentWhiteLabel\Pages\ManageWhiteLabel;
use MuazzamBuilds\FilamentWhiteLabel\Support\FontOptions;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentWhiteLabelServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-white-label';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile('filament-white-label')
            ->hasTranslations()
            ->hasViews('filament-white-label')
            ->hasMigration('create_filament_white_label_settings_table')
            ->runsMigrations();
    }

    public function packageBooted(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => FontOptions::previewStylesheetHtml()->toHtml(),
            scopes: ManageWhiteLabel::class,
        );
    }
}
