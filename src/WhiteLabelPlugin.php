<?php

namespace MuazzamBuilds\FilamentWhiteLabel;

use Closure;
use Filament\Contracts\Plugin;
use Filament\FontProviders\BunnyFontProvider;
use Filament\Panel;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use MuazzamBuilds\FilamentWhiteLabel\Pages\ManageWhiteLabel;
use MuazzamBuilds\FilamentWhiteLabel\Services\WhiteLabelManager;
use Throwable;
use UnitEnum;

class WhiteLabelPlugin implements Plugin
{
    protected string | Closure | null $navigationLabel = null;

    protected string | UnitEnum | Closure | null $navigationGroup = null;

    protected string | Closure | null $navigationIcon = null;

    protected int | Closure | null $navigationSort = null;

    protected bool | Closure $shouldRegisterNavigation = true;

    protected bool | Closure $authorize = true;

    protected bool | Closure $enabled = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-white-label';
    }

    public function register(Panel $panel): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $panelId = $panel->getId();

        // Must be registered before Panel::boot() calls FilamentColor::register($panel->getColors()).
        $panel->colors(fn (): array => $this->safe(
            fn (): array => app(WhiteLabelManager::class)->colors($panelId),
            [],
        ));

        $panel->pages([
            ManageWhiteLabel::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $panelId = $panel->getId();

            // Panel::boot() registers colors before plugins boot — push ours into ColorManager too
            // so they still win even if something evaluated the panel colors early.
            FilamentColor::register(fn (): array => $this->safe(
                fn (): array => app(WhiteLabelManager::class)->colors($panelId),
                [],
            ));

            $this->applyWhiteLabel($panel);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function applyWhiteLabel(Panel $panel): void
    {
        $panelId = $panel->getId();
        $manager = app(WhiteLabelManager::class);

        // Capture provider defaults before replacing with live closures.
        $fallbackBrandName = $panel->getBrandName();
        $fallbackBrandLogo = $panel->getBrandLogo();
        $fallbackDarkBrandLogo = $panel->getDarkModeBrandLogo();
        $fallbackBrandLogoHeight = $panel->getBrandLogoHeight();
        $fallbackFavicon = $panel->getFavicon();

        $panel
            ->brandName(fn (): mixed => $this->safe(fn (): mixed => $manager->brandName($panelId) ?? $fallbackBrandName, $fallbackBrandName))
            ->brandLogo(fn (): mixed => $this->safe(fn (): mixed => $manager->brandLogoUrl($panelId) ?? $fallbackBrandLogo, $fallbackBrandLogo))
            ->darkModeBrandLogo(fn (): mixed => $this->safe(fn (): mixed => $manager->darkBrandLogoUrl($panelId) ?? $fallbackDarkBrandLogo, $fallbackDarkBrandLogo))
            ->brandLogoHeight(fn (): ?string => $this->safe(fn (): ?string => $manager->brandLogoHeight($panelId) ?? $fallbackBrandLogoHeight, $fallbackBrandLogoHeight))
            ->favicon(fn (): ?string => $this->safe(fn (): ?string => $manager->faviconUrl($panelId) ?? $fallbackFavicon, $fallbackFavicon))
            ->topNavigation(fn (): bool => (bool) $this->safe(fn (): mixed => $manager->getValue($panelId, 'layout.top_navigation', false), false))
            ->topbar(fn (): bool => (bool) $this->safe(fn (): mixed => $manager->getValue($panelId, 'layout.topbar', true), true))
            ->breadcrumbs(fn (): bool => (bool) $this->safe(fn (): mixed => $manager->getValue($panelId, 'layout.breadcrumbs', true), true))
            ->sidebarCollapsibleOnDesktop(fn (): bool => (bool) $this->safe(fn (): mixed => $manager->getValue($panelId, 'layout.sidebar_collapsible_on_desktop', true), true))
            ->sidebarFullyCollapsibleOnDesktop(fn (): bool => (bool) $this->safe(fn (): mixed => $manager->getValue($panelId, 'layout.sidebar_fully_collapsible_on_desktop', false), false))
            ->collapsibleNavigationGroups(fn (): bool => (bool) $this->safe(fn (): mixed => $manager->getValue($panelId, 'layout.collapsible_navigation_groups', true), true))
            ->spa(
                condition: fn (): bool => (bool) $this->safe(fn (): mixed => $manager->getValue($panelId, 'behavior.spa', false), false),
                hasPrefetching: fn (): bool => (bool) $this->safe(fn (): mixed => $manager->getValue($panelId, 'behavior.spa_prefetching', false), false),
            )
            ->unsavedChangesAlerts(fn (): bool => (bool) $this->safe(fn (): mixed => $manager->getValue($panelId, 'behavior.unsaved_changes_alerts', false), false))
            ->databaseNotifications(fn (): bool => $this->safe(
                fn (): bool => $manager->databaseNotificationsEnabled($panelId),
                false,
            ));

        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString($this->safe(
                fn (): string => $this->renderCustomCss($manager, $panelId),
                '',
            ))
        );
        FilamentView::registerRenderHook(
            PanelsRenderHook::FOOTER,
            fn (): string => $this->safe(
                fn (): string => $this->renderFooter($manager, $panelId),
                '',
            )
        );

        $sidebarWidth = $this->safe(fn (): mixed => $manager->getValue($panelId, 'layout.sidebar_width'), null);

        if (filled($sidebarWidth) && is_string($sidebarWidth)) {
            $panel->sidebarWidth($sidebarWidth);
        }

        $maxContentWidth = $this->safe(fn (): mixed => $manager->getValue($panelId, 'layout.max_content_width'), null);

        if (filled($maxContentWidth) && is_string($maxContentWidth)) {
            $width = Width::tryFrom($maxContentWidth) ?? $maxContentWidth;
            $panel->maxContentWidth($width);
        }

        $fontFamily = $this->safe(fn (): ?string => $manager->fontFamily($panelId), null);

        if (filled($fontFamily)) {
            $panel->font($fontFamily, provider: BunnyFontProvider::class);
        }

        $darkMode = $this->safe(fn (): mixed => $manager->getValue($panelId, 'behavior.dark_mode'), null);

        if ($darkMode !== null) {
            $panel->darkMode((bool) $darkMode);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  T  $fallback
     * @return T
     */
    protected function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }

    public function enabled(bool | Closure $condition = true): static
    {
        $this->enabled = $condition;

        return $this;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->evaluate($this->enabled);
    }

    public function navigationLabel(string | Closure | null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationGroup(string | UnitEnum | Closure | null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string | Closure | null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationSort(int | Closure | null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function registerNavigation(bool | Closure $condition = true): static
    {
        $this->shouldRegisterNavigation = $condition;

        return $this;
    }

    public function authorize(bool | Closure $condition = true): static
    {
        $this->authorize = $condition;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        return (string) ($this->evaluate($this->navigationLabel)
            ?? __('filament-white-label::messages.navigation_label'));
    }

    public function getNavigationGroup(): string | UnitEnum | null
    {
        $group = $this->evaluate($this->navigationGroup);

        if ($group !== null) {
            return $group;
        }

        return config('filament-white-label.navigation_group');
    }

    public function getNavigationIcon(): ?string
    {
        return $this->evaluate($this->navigationIcon)
            ?? config('filament-white-label.navigation_icon');
    }

    public function getNavigationSort(): ?int
    {
        $sort = $this->evaluate($this->navigationSort);

        if ($sort !== null) {
            return (int) $sort;
        }

        $config = config('filament-white-label.navigation_sort');

        return is_numeric($config) ? (int) $config : null;
    }

    public function shouldRegisterNavigation(): bool
    {
        return (bool) $this->evaluate($this->shouldRegisterNavigation);
    }

    public function canAccess(): bool
    {
        return (bool) $this->evaluate($this->authorize);
    }

    protected function renderCustomCss(WhiteLabelManager $manager, string $panelId): string
    {
        $css = $manager->customCss($panelId);

        if (! filled($css)) {
            return '';
        }

        return '<style data-filament-white-label>' . $css . '</style>';
    }

    protected function renderFooter(WhiteLabelManager $manager, string $panelId): string
    {
        if (! $manager->footerEnabled($panelId)) {
            return '';
        }

        $text = $manager->footerText($panelId);

        if (! filled($text)) {
            return '';
        }

        return view('filament-white-label::footer', [
            'text' => $text,
        ])->render();
    }

    protected function evaluate(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }
}
