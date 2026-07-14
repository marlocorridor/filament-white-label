<?php

namespace MuazzamBuilds\FilamentWhiteLabel\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use MuazzamBuilds\FilamentWhiteLabel\Services\WhiteLabelManager;
use MuazzamBuilds\FilamentWhiteLabel\WhiteLabelPlugin;

class WhiteLabelPluginTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('filament-white-label.cache.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('filament_white_label_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('panel_id');
            $table->string('tenant_key')->default('');
            $table->json('data');
            $table->timestamps();
            $table->unique(['panel_id', 'tenant_key']);
        });
    }

    public function test_plugin_defaults(): void
    {
        $plugin = WhiteLabelPlugin::make()
            ->navigationLabel('Branding')
            ->navigationGroup('System');

        $this->assertSame('filament-white-label', $plugin->getId());
        $this->assertSame('Branding', $plugin->getNavigationLabel());
        $this->assertSame('System', $plugin->getNavigationGroup());
        $this->assertTrue($plugin->canAccess());
        $this->assertTrue($plugin->isEnabled());
    }

    public function test_manager_saves_and_reads_settings(): void
    {
        $manager = app(WhiteLabelManager::class);

        $manager->save('admin', [
            'brand_name' => 'Acme Admin',
            'colors' => [
                'primary' => '#f59e0b',
            ],
            'footer' => [
                'enabled' => true,
                'text' => 'Powered by Acme',
            ],
        ]);

        $this->assertSame('Acme Admin', $manager->brandName('admin'));
        $this->assertTrue($manager->footerEnabled('admin'));
        $this->assertSame('Powered by Acme', $manager->footerText('admin'));
        $this->assertSame('#f59e0b', $manager->colors('admin')['primary']);
        $this->assertSame('#f59e0b', $manager->normalizeHex('f59e0b'));
        $this->assertNull($manager->normalizeHex('not-a-color'));
    }

    public function test_custom_css_is_sanitized(): void
    {
        $manager = app(WhiteLabelManager::class);

        $manager->save('admin', [
            'custom_css' => "@import url('evil.css'); body { color: red; } a { behavior: url(x); }",
        ]);

        $css = $manager->customCss('admin');

        $this->assertStringNotContainsString('@import', (string) $css);
        $this->assertStringNotContainsString('behavior', (string) $css);
        $this->assertStringContainsString('color: red', (string) $css);
    }

    public function test_database_notifications_require_notifications_table(): void
    {
        $manager = app(WhiteLabelManager::class);

        $this->assertFalse(\MuazzamBuilds\FilamentWhiteLabel\Support\EnvironmentChecks::notificationsTableReady());
        $this->assertFalse($manager->databaseNotificationsEnabled('admin'));

        $sanitized = $manager->sanitizeForSave([
            'behavior' => [
                'database_notifications' => true,
            ],
        ]);

        $this->assertFalse($sanitized['data']['behavior']['database_notifications']);
        $this->assertNotEmpty($sanitized['warnings']);
    }

    public function test_database_notifications_enable_when_table_exists(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $this->assertTrue(\MuazzamBuilds\FilamentWhiteLabel\Support\EnvironmentChecks::notificationsTableReady());

        $manager = app(WhiteLabelManager::class);
        $manager->save('admin', [
            'behavior' => [
                'database_notifications' => true,
            ],
        ]);

        // Auth model is not configured as Notifiable in testbench by default,
        // so enabling still requires both table + Notifiable — assert table alone is not enough.
        $this->assertFalse($manager->databaseNotificationsEnabled('admin'));
    }

    public function test_font_preview_options_include_font_family_style(): void
    {
        $options = \MuazzamBuilds\FilamentWhiteLabel\Support\FontOptions::previewOptions();

        $this->assertArrayHasKey('Inter', $options);
        $this->assertStringContainsString("font-family: 'Inter'", $options['Inter']);
        $this->assertStringContainsString('>Inter</span>', $options['Inter']);
        $this->assertStringContainsString('fonts.bunny.net', \MuazzamBuilds\FilamentWhiteLabel\Support\FontOptions::previewStylesheetUrl());
    }
}
