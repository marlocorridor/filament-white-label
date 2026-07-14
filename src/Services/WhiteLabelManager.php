<?php

namespace MuazzamBuilds\FilamentWhiteLabel\Services;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use MuazzamBuilds\FilamentWhiteLabel\Models\WhiteLabelSetting;
use MuazzamBuilds\FilamentWhiteLabel\Support\ColorRoles;
use MuazzamBuilds\FilamentWhiteLabel\Support\EnvironmentChecks;
use RuntimeException;
use Throwable;

class WhiteLabelManager
{
    /**
     * @return array<string, mixed>
     */
    public function get(string $panelId, ?string $tenantKey = null): array
    {
        $tenantKey ??= $this->resolveTenantKey();
        $defaults = config('filament-white-label.defaults', []);

        try {
            if (! $this->tableReady()) {
                return $defaults;
            }
        } catch (Throwable) {
            return $defaults;
        }

        $record = $this->remember($panelId, $tenantKey, function () use ($panelId, $tenantKey): ?array {
            $row = WhiteLabelSetting::query()
                ->where('panel_id', $panelId)
                ->where('tenant_key', $tenantKey)
                ->first();

            if ($row === null && $tenantKey !== '') {
                $row = WhiteLabelSetting::query()
                    ->where('panel_id', $panelId)
                    ->where('tenant_key', '')
                    ->first();
            }

            return $row?->data;
        });

        return $this->mergeDefaults($defaults, $record ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, warnings: list<string>}
     */
    public function sanitizeForSave(array $data): array
    {
        $warnings = [];

        if (($data['layout']['max_content_width'] ?? null) === '') {
            $data['layout']['max_content_width'] = null;
        }

        if ((bool) Arr::get($data, 'behavior.database_notifications', false)
            && ! EnvironmentChecks::canEnableDatabaseNotifications()) {
            Arr::set($data, 'behavior.database_notifications', false);
            $warnings[] = EnvironmentChecks::databaseNotificationsBlocker()
                ?? __('filament-white-label::messages.checks.database_notifications_disabled');
        }

        if ((bool) Arr::get($data, 'behavior.spa_prefetching', false)
            && ! (bool) Arr::get($data, 'behavior.spa', false)) {
            Arr::set($data, 'behavior.spa_prefetching', false);
            $warnings[] = __('filament-white-label::messages.checks.spa_prefetch_requires_spa');
        }

        return [
            'data' => $data,
            'warnings' => $warnings,
        ];
    }

    /**
     * Only true when the setting is on and the environment can safely run DB notifications.
     */
    public function databaseNotificationsEnabled(string $panelId, ?string $tenantKey = null): bool
    {
        if (! EnvironmentChecks::canEnableDatabaseNotifications()) {
            return false;
        }

        return (bool) $this->getValue($panelId, 'behavior.database_notifications', false, $tenantKey);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException
     */
    public function save(string $panelId, array $data, ?string $tenantKey = null): WhiteLabelSetting
    {
        if (! EnvironmentChecks::settingsTableReady()) {
            throw new RuntimeException(__('filament-white-label::messages.checks.settings_table_missing'));
        }

        $tenantKey ??= $this->resolveTenantKey();
        $sanitized = $this->sanitizeForSave($data);

        $setting = WhiteLabelSetting::query()->updateOrCreate(
            [
                'panel_id' => $panelId,
                'tenant_key' => $tenantKey,
            ],
            [
                'data' => $this->mergeDefaults(config('filament-white-label.defaults', []), $sanitized['data']),
            ],
        );

        $this->forget($panelId, $tenantKey);

        if ($tenantKey !== '') {
            $this->forget($panelId, '');
        }

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{setting: WhiteLabelSetting, warnings: list<string>}
     *
     * @throws RuntimeException
     */
    public function saveWithFeedback(string $panelId, array $data, ?string $tenantKey = null): array
    {
        $sanitized = $this->sanitizeForSave($data);
        $setting = $this->save($panelId, $sanitized['data'], $tenantKey);

        return [
            'setting' => $setting,
            'warnings' => $sanitized['warnings'],
        ];
    }

    public function getValue(string $panelId, string $key, mixed $default = null, ?string $tenantKey = null): mixed
    {
        return Arr::get($this->get($panelId, $tenantKey), $key, $default);
    }

    public function brandName(string $panelId): ?string
    {
        $name = $this->getValue($panelId, 'brand_name');

        return filled($name) ? (string) $name : null;
    }

    public function brandLogoUrl(string $panelId): ?string
    {
        return $this->mediaUrl($this->getValue($panelId, 'brand_logo'));
    }

    public function darkBrandLogoUrl(string $panelId): ?string
    {
        return $this->mediaUrl($this->getValue($panelId, 'dark_brand_logo'));
    }

    public function brandLogoHeight(string $panelId): ?string
    {
        $height = $this->getValue($panelId, 'brand_logo_height');

        return filled($height) ? (string) $height : null;
    }

    public function faviconUrl(string $panelId): ?string
    {
        return $this->mediaUrl($this->getValue($panelId, 'favicon'));
    }

    public function fontFamily(string $panelId): ?string
    {
        $font = $this->getValue($panelId, 'font_family');

        return filled($font) ? (string) $font : null;
    }

    /**
     * Hex colors for FilamentColor / Panel::colors().
     * Returns strings so ColorManager generates the full shade palette.
     *
     * @return array<string, string>
     */
    public function colors(string $panelId): array
    {
        $colors = [];

        foreach (ColorRoles::all() as $role) {
            $hex = $this->normalizeHex($this->getValue($panelId, "colors.{$role}"));

            if ($hex === null) {
                continue;
            }

            $colors[$role] = $hex;
        }

        return $colors;
    }

    /**
     * Full shade palettes (optional; prefer colors() hex strings for registration).
     *
     * @return array<string, array<int, string>>
     */
    public function colorPalettes(string $panelId): array
    {
        $palettes = [];

        foreach ($this->colors($panelId) as $role => $hex) {
            try {
                $palettes[$role] = Color::hex($hex);
            } catch (Throwable) {
                // Ignore invalid hex.
            }
        }

        return $palettes;
    }

    public function normalizeHex(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $value = trim($value);

        if (! str_starts_with($value, '#')) {
            $value = '#' . $value;
        }

        if (! preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/', $value)) {
            return null;
        }

        return $value;
    }

    public function customCss(string $panelId): ?string
    {
        $css = $this->getValue($panelId, 'custom_css');

        if (! filled($css)) {
            return null;
        }

        return $this->sanitizeCss((string) $css);
    }

    public function footerEnabled(string $panelId): bool
    {
        return (bool) $this->getValue($panelId, 'footer.enabled', false);
    }

    public function footerText(string $panelId): ?string
    {
        $text = $this->getValue($panelId, 'footer.text');

        return filled($text) ? (string) $text : null;
    }

    public function forget(string $panelId, ?string $tenantKey = null): void
    {
        if (! config('filament-white-label.cache.enabled', true)) {
            return;
        }

        Cache::forget($this->cacheKey($panelId, $tenantKey ?? $this->resolveTenantKey()));
    }

    public function resolveTenantKey(): string
    {
        try {
            $tenant = Filament::getTenant();
        } catch (Throwable) {
            return '';
        }

        if ($tenant === null) {
            return '';
        }

        return (string) $tenant->getKey();
    }

    protected function tableReady(): bool
    {
        return Schema::hasTable((new WhiteLabelSetting)->getTable());
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeDefaults(array $defaults, array $data): array
    {
        return array_replace_recursive($defaults, $data);
    }

    /**
     * @param  callable(): (?array<string, mixed>)  $callback
     * @return array<string, mixed>|null
     */
    protected function remember(string $panelId, string $tenantKey, callable $callback): ?array
    {
        if (! config('filament-white-label.cache.enabled', true)) {
            return $callback();
        }

        /** @var array<string, mixed>|null $result */
        $result = Cache::remember(
            $this->cacheKey($panelId, $tenantKey),
            (int) config('filament-white-label.cache.ttl', 3600),
            $callback,
        );

        return $result;
    }

    protected function cacheKey(string $panelId, string $tenantKey): string
    {
        $prefix = (string) config('filament-white-label.cache.key_prefix', 'filament-white-label');

        return "{$prefix}.{$panelId}." . ($tenantKey === '' ? 'global' : $tenantKey);
    }

    protected function mediaUrl(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = Arr::first($path);
        }

        if (! filled($path) || ! is_string($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        $disk = (string) config('filament-white-label.disk', 'public');

        try {
            return Storage::disk($disk)->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    protected function sanitizeCss(string $css): string
    {
        $max = (int) config('filament-white-label.custom_css_max_length', 50000);
        $css = mb_substr($css, 0, $max);

        $css = preg_replace('/@import\b[^;]*;?/i', '', $css) ?? $css;
        $css = preg_replace('/expression\s*\(/i', '', $css) ?? $css;
        $css = preg_replace('/javascript\s*:/i', '', $css) ?? $css;
        $css = preg_replace('/behavior\s*:/i', '', $css) ?? $css;

        return trim($css);
    }
}
