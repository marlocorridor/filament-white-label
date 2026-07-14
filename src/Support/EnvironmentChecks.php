<?php

namespace MuazzamBuilds\FilamentWhiteLabel\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class EnvironmentChecks
{
    public static function notificationsTableReady(): bool
    {
        try {
            return Schema::hasTable('notifications');
        } catch (Throwable) {
            return false;
        }
    }

    public static function authModelIsNotifiable(): bool
    {
        try {
            $guard = Filament::getCurrentPanel()?->getAuthGuard()
                ?? config('auth.defaults.guard', 'web');

            $provider = config("auth.guards.{$guard}.provider");
            $modelClass = config("auth.providers.{$provider}.model");

            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                return false;
            }

            if (! is_subclass_of($modelClass, Model::class)) {
                return false;
            }

            return in_array(Notifiable::class, class_uses_recursive($modelClass), true);
        } catch (Throwable) {
            return false;
        }
    }

    public static function canEnableDatabaseNotifications(): bool
    {
        return self::notificationsTableReady() && self::authModelIsNotifiable();
    }

    /**
     * Human-readable reason when database notifications cannot be enabled.
     */
    public static function databaseNotificationsBlocker(): ?string
    {
        if (! self::notificationsTableReady()) {
            return __('filament-white-label::messages.checks.notifications_table_missing');
        }

        if (! self::authModelIsNotifiable()) {
            return __('filament-white-label::messages.checks.auth_not_notifiable');
        }

        return null;
    }

    public static function settingsTableReady(): bool
    {
        try {
            return Schema::hasTable('filament_white_label_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
