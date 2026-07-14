<?php

namespace MuazzamBuilds\FilamentWhiteLabel\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use MuazzamBuilds\FilamentWhiteLabel\Services\WhiteLabelManager;
use MuazzamBuilds\FilamentWhiteLabel\Support\ColorRoles;
use MuazzamBuilds\FilamentWhiteLabel\Support\EnvironmentChecks;
use MuazzamBuilds\FilamentWhiteLabel\Support\FontOptions;
use MuazzamBuilds\FilamentWhiteLabel\WhiteLabelPlugin;
use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageWhiteLabel extends Page
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $slug = 'white-label';

    public static function getNavigationLabel(): string
    {
        return WhiteLabelPlugin::get()->getNavigationLabel();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return WhiteLabelPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        $icon = WhiteLabelPlugin::get()->getNavigationIcon();

        return $icon ?? static::$navigationIcon;
    }

    public static function getNavigationSort(): ?int
    {
        return WhiteLabelPlugin::get()->getNavigationSort();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return WhiteLabelPlugin::get()->shouldRegisterNavigation()
            && WhiteLabelPlugin::get()->canAccess();
    }

    public static function canAccess(): bool
    {
        return WhiteLabelPlugin::get()->canAccess();
    }

    public function getTitle(): string | Htmlable
    {
        return __('filament-white-label::messages.title');
    }

    public function getHeading(): string | Htmlable | null
    {
        return __('filament-white-label::messages.heading');
    }

    public function getSubheading(): string | Htmlable | null
    {
        return __('filament-white-label::messages.subheading');
    }

    public function mount(): void
    {
        try {
            $panel = Filament::getCurrentPanel();

            if ($panel === null) {
                $this->form->fill(config('filament-white-label.defaults', []));

                return;
            }

            $data = app(WhiteLabelManager::class)->get($panel->getId());

            // Never present an enabled DB-notifications toggle that would crash the panel.
            if (! EnvironmentChecks::canEnableDatabaseNotifications()) {
                data_set($data, 'behavior.database_notifications', false);
            }

            $this->form->fill($data);
        } catch (Throwable $exception) {
            report($exception);

            $this->form->fill(config('filament-white-label.defaults', []));

            Notification::make()
                ->title(__('filament-white-label::messages.checks.load_failed_title'))
                ->body(__('filament-white-label::messages.checks.load_failed_body'))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $disk = (string) config('filament-white-label.disk', 'public');
        $directory = (string) config('filament-white-label.directory', 'white-label');

        return $schema
            ->components([
                Tabs::make('white-label')
                    ->persistTabInQueryString('tab')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('filament-white-label::messages.tabs.brand'))
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('brand_name')
                                            ->label(__('filament-white-label::messages.fields.brand_name'))
                                            ->maxLength(120),
                                        FileUpload::make('brand_logo')
                                            ->label(__('filament-white-label::messages.fields.brand_logo'))
                                            ->image()
                                            ->disk($disk)
                                            ->directory($directory . '/logos')
                                            ->visibility('public')
                                            ->imagePreviewHeight('80'),
                                        FileUpload::make('dark_brand_logo')
                                            ->label(__('filament-white-label::messages.fields.dark_brand_logo'))
                                            ->image()
                                            ->disk($disk)
                                            ->directory($directory . '/logos')
                                            ->visibility('public')
                                            ->imagePreviewHeight('80'),
                                        TextInput::make('brand_logo_height')
                                            ->label(__('filament-white-label::messages.fields.brand_logo_height'))
                                            ->helperText(__('filament-white-label::messages.helpers.brand_logo_height'))
                                            ->placeholder('2rem'),
                                        FileUpload::make('favicon')
                                            ->label(__('filament-white-label::messages.fields.favicon'))
                                            ->image()
                                            ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/svg+xml', 'image/webp', 'image/jpeg'])
                                            ->disk($disk)
                                            ->directory($directory . '/favicons')
                                            ->visibility('public')
                                            ->imagePreviewHeight('64'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make(__('filament-white-label::messages.tabs.colors'))
                            ->icon(Heroicon::OutlinedSwatch)
                            ->schema([
                                Section::make()
                                    ->description(__('filament-white-label::messages.helpers.colors'))
                                    ->schema(
                                        collect(ColorRoles::all())
                                            ->map(fn (string $role): ColorPicker => ColorPicker::make("colors.{$role}")
                                                ->label(__("filament-white-label::messages.fields.color_{$role}"))
                                                ->hex())
                                            ->all()
                                    )
                                    ->columns(3),
                            ]),

                        Tab::make(__('filament-white-label::messages.tabs.typography'))
                            ->icon(Heroicon::OutlinedLanguage)
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Select::make('font_family')
                                            ->label(__('filament-white-label::messages.fields.font_family'))
                                            ->helperText(__('filament-white-label::messages.helpers.font_family'))
                                            ->options(FontOptions::previewOptions())
                                            ->allowHtml()
                                            ->searchable()
                                            ->native(false)
                                            ->nullable(),
                                    ]),
                            ]),

                        Tab::make(__('filament-white-label::messages.tabs.layout'))
                            ->icon(Heroicon::OutlinedSquares2x2)
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Toggle::make('layout.top_navigation')
                                            ->label(__('filament-white-label::messages.fields.top_navigation')),
                                        Toggle::make('layout.topbar')
                                            ->label(__('filament-white-label::messages.fields.topbar')),
                                        Toggle::make('layout.breadcrumbs')
                                            ->label(__('filament-white-label::messages.fields.breadcrumbs')),
                                        Toggle::make('layout.sidebar_collapsible_on_desktop')
                                            ->label(__('filament-white-label::messages.fields.sidebar_collapsible_on_desktop')),
                                        Toggle::make('layout.sidebar_fully_collapsible_on_desktop')
                                            ->label(__('filament-white-label::messages.fields.sidebar_fully_collapsible_on_desktop')),
                                        Toggle::make('layout.collapsible_navigation_groups')
                                            ->label(__('filament-white-label::messages.fields.collapsible_navigation_groups')),
                                        TextInput::make('layout.sidebar_width')
                                            ->label(__('filament-white-label::messages.fields.sidebar_width'))
                                            ->helperText(__('filament-white-label::messages.helpers.sidebar_width'))
                                            ->placeholder('18rem'),
                                        Select::make('layout.max_content_width')
                                            ->label(__('filament-white-label::messages.fields.max_content_width'))
                                            ->options([
                                                '' => __('filament-white-label::messages.width.default'),
                                                Width::SevenExtraLarge->value => __('filament-white-label::messages.width.7xl'),
                                                Width::Full->value => __('filament-white-label::messages.width.full'),
                                                Width::Screen->value => __('filament-white-label::messages.width.screen'),
                                                Width::FiveExtraLarge->value => '5xl',
                                                Width::SixExtraLarge->value => '6xl',
                                            ])
                                            ->nullable(),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make(__('filament-white-label::messages.tabs.behavior'))
                            ->icon(Heroicon::OutlinedBolt)
                            ->schema([
                                ...(! EnvironmentChecks::canEnableDatabaseNotifications()
                                    ? [
                                        Callout::make(__('filament-white-label::messages.checks.database_notifications_unavailable_title'))
                                            ->description(EnvironmentChecks::databaseNotificationsBlocker())
                                            ->warning()
                                            ->icon(Heroicon::OutlinedExclamationTriangle)
                                            ->columnSpanFull(),
                                    ]
                                    : []),
                                Section::make()
                                    ->schema([
                                        Toggle::make('behavior.spa')
                                            ->label(__('filament-white-label::messages.fields.spa'))
                                            ->live(),
                                        Toggle::make('behavior.spa_prefetching')
                                            ->label(__('filament-white-label::messages.fields.spa_prefetching'))
                                            ->helperText(__('filament-white-label::messages.helpers.spa_prefetching'))
                                            ->disabled(fn (Get $get): bool => ! (bool) $get('behavior.spa')),
                                        Toggle::make('behavior.unsaved_changes_alerts')
                                            ->label(__('filament-white-label::messages.fields.unsaved_changes_alerts')),
                                        Toggle::make('behavior.database_notifications')
                                            ->label(__('filament-white-label::messages.fields.database_notifications'))
                                            ->helperText(
                                                EnvironmentChecks::canEnableDatabaseNotifications()
                                                    ? __('filament-white-label::messages.helpers.database_notifications')
                                                    : EnvironmentChecks::databaseNotificationsBlocker()
                                            )
                                            ->disabled(fn (): bool => ! EnvironmentChecks::canEnableDatabaseNotifications())
                                            ->dehydrated(),
                                        Toggle::make('behavior.dark_mode')
                                            ->label(__('filament-white-label::messages.fields.dark_mode')),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make(__('filament-white-label::messages.tabs.footer'))
                            ->icon(Heroicon::OutlinedBars3BottomLeft)
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Toggle::make('footer.enabled')
                                            ->label(__('filament-white-label::messages.fields.footer_enabled')),
                                        Textarea::make('footer.text')
                                            ->label(__('filament-white-label::messages.fields.footer_text'))
                                            ->helperText(__('filament-white-label::messages.helpers.footer_text'))
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make(__('filament-white-label::messages.tabs.advanced'))
                            ->icon(Heroicon::OutlinedCodeBracket)
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('custom_css')
                                            ->label(__('filament-white-label::messages.fields.custom_css'))
                                            ->helperText(__('filament-white-label::messages.helpers.custom_css'))
                                            ->rows(12)
                                            ->extraInputAttributes(['class' => 'font-mono text-sm'])
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        try {
            $panel = Filament::getCurrentPanel();

            if ($panel === null) {
                throw new RuntimeException(__('filament-white-label::messages.checks.panel_missing'));
            }

            if (! EnvironmentChecks::settingsTableReady()) {
                Notification::make()
                    ->title(__('filament-white-label::messages.checks.settings_table_missing_title'))
                    ->body(__('filament-white-label::messages.checks.settings_table_missing'))
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }

            $data = $this->form->getState();

            if ((bool) ($data['behavior']['database_notifications'] ?? false)
                && ! EnvironmentChecks::canEnableDatabaseNotifications()) {
                throw ValidationException::withMessages([
                    'data.behavior.database_notifications' => EnvironmentChecks::databaseNotificationsBlocker()
                        ?? __('filament-white-label::messages.checks.database_notifications_disabled'),
                ]);
            }

            $result = app(WhiteLabelManager::class)->saveWithFeedback($panel->getId(), $data);

            // Keep form in sync if a guard flipped a toggle off.
            $this->form->fill(
                app(WhiteLabelManager::class)->get($panel->getId()),
            );

            if ($result['warnings'] !== []) {
                foreach ($result['warnings'] as $warning) {
                    Notification::make()
                        ->title(__('filament-white-label::messages.checks.setting_adjusted_title'))
                        ->body($warning)
                        ->warning()
                        ->send();
                }
            }

            Notification::make()
                ->title(__('filament-white-label::messages.actions.saved'))
                ->success()
                ->send();

            // Theme colors / layout are resolved at panel boot — hard reload so CSS vars update.
            $this->redirect(static::getUrl(), navigate: false);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('filament-white-label::messages.checks.save_failed_title'))
                ->body($exception->getMessage() ?: __('filament-white-label::messages.checks.save_failed_body'))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label(__('filament-white-label::messages.actions.save'))
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ])
                    ->alignment(Alignment::Start)
                    ->key('form-actions'),
            ]);
    }
}
