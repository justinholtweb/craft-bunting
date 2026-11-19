<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting;

use CraftCms\Cms\Condition\Events\ConditionRulesResolving;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\Gql\GqlQueries;
use CraftCms\Cms\Http\Middleware\HandleTemplateRequest;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Controls\IconPicker;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\User\Data\Permission;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Compilers\BladeCompiler;
use JustinHoltWeb\Bunting\Flags\Flags;
use JustinHoltWeb\Bunting\Pennant\BuntingDriver;
use JustinHoltWeb\Bunting\Pennant\ScopeFactory;
use Laravel\Pennant\Drivers\DatabaseDriver;
use Laravel\Pennant\FeatureManager;
use Override;

use function CraftCms\Cms\t;

/**
 * Bunting — feature flags for Craft on Laravel Pennant. Flip them in the CP and
 * scope them to sites, user groups, users or a percentage.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const string PERMISSION_EDIT = 'bunting:editFlags';

    public const string PERMISSION_AUDIT = 'bunting:viewAudit';

    public bool $hasCpSettings = true;

    public bool $hasCpSection = true;

    protected array $events = [
        GqlSchemaComponentsResolving::class => Listeners\RegisterGqlComponents::class,
    ];

    protected array $commands = [
        Commands\ListCommand::class,
        Commands\SetCommand::class,
        Commands\CheckCommand::class,
        Commands\PurgeCommand::class,
        Commands\StaleCommand::class,
    ];

    public function boot(): void
    {
        $this->app->bind(Settings::class, fn (): Settings => $this->getSettings());
        $this->app->scoped(Flags::class);
        $this->app->scoped(Bunting::class);
        $this->app->scoped(Preview::class);
        $this->app->scoped(ScopeFactory::class);

        Event::subscribe(Listeners\RegisterTemplateApi::class);
        Event::subscribe(Listeners\Housekeeping::class);
        $this->app->singleton(Listeners\KeepFlagsOutOfGqlCache::class);
        Event::subscribe(Listeners\KeepFlagsOutOfGqlCache::class);
        Event::listen(ConditionRulesResolving::class, function (ConditionRulesResolving $event): void {
            if ($event->condition instanceof ElementConditionInterface) {
                $event->conditionRules[] = Conditions\FeatureActiveConditionRule::class;
            }
        });

        $this->registerPennant();
        $this->registerMiddleware();
        $this->registerBlade();
        $this->app->make(GqlQueries::class)->register(Gql\FlagsQuery::class);

        $path = Flags::CONFIG_PATH.'.{uid}';
        ProjectConfig::onAdd($path, fn (ConfigEvent $e) => app(Flags::class)->handleChangedFlag($e))
            ->onUpdate($path, fn (ConfigEvent $e) => app(Flags::class)->handleChangedFlag($e))
            ->onRemove($path, fn (ConfigEvent $e) => app(Flags::class)->handleDeletedFlag($e));
    }

    /**
     * Pennant store `bunting` (the default store unless the setting is off), on
     * Bunting's own `bunting_features` table. Its default scope is the current
     * request's site and user — Craft's, not the auth guard's.
     */
    private function registerPennant(): void
    {
        config([
            'pennant.stores.bunting' => ['driver' => 'bunting', 'connection' => null, 'table' => 'bunting_features'],
        ]);

        if ($this->getSettings()->defaultStore) {
            config(['pennant.default' => Bunting::STORE]);
        }

        $this->callAfterResolving(FeatureManager::class, function (FeatureManager $manager): void {
            $manager->extend('bunting', fn ($app) => new BuntingDriver(
                new DatabaseDriver($app['db'], $app['events'], $app['config'], Bunting::STORE, []),
            ));
            $manager->resolveScopeUsing(fn (string $driver) => $driver === Bunting::STORE
                ? app(ScopeFactory::class)->current()
                : auth()->guard()->user());
        });
    }

    /**
     * `bunting:<flag>` route middleware, and URI gating + previews on front-end
     * requests (before Craft renders a template, so a gated page never renders).
     */
    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('bunting', Http\Middleware\RequireFeature::class);

        $group = $router->getMiddlewareGroups()['craft.web'] ?? [];

        if (! in_array(Http\Middleware\GateSiteRequests::class, $group, true)) {
            $at = array_search(HandleTemplateRequest::class, $group, true);
            array_splice($group, $at === false ? count($group) : (int) $at, 0, [Http\Middleware\GateSiteRequests::class]);
            $router->middlewareGroup('craft.web', $group);
        }
    }

    /**
     * Blade gets Pennant's own `@feature` / `@featureany`; Bunting adds `@requireFeature`
     * (Twig: `{% requireFeature %}`). Both call {@see Bunting::requireFeature()}.
     */
    private function registerBlade(): void
    {
        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade): void {
            $blade->directive('requireFeature', fn (string $expression): string => '<?php app(\\'.Bunting::class.'::class)->requireFeature(...array_map(\'strval\', (array) ('.($expression !== '' ? $expression : '[]').'))); ?>');
        });
    }

    #[Override]
    protected function afterInstall(): void
    {
        // Flags already in project config (e.g. installing on another environment).
        app(Flags::class)->syncFromProjectConfig();
    }

    #[Override]
    protected function getPermissions(): array
    {
        return [
            new Permission(self::PERMISSION_EDIT, t('Edit flags and their rollout', category: 'bunting'), t('Includes per-user overrides and purging stored values.', category: 'bunting')),
            new Permission(self::PERMISSION_AUDIT, t('View the audit log', category: 'bunting')),
            new Permission(Preview::PERMISSION, t('Preview flags with `?bunting-preview=`', category: 'bunting'), t('Turns flags on or off for the person’s own session only.', category: 'bunting')),
        ];
    }

    #[Override]
    public function getCpNavItem(): NavItem|array|null
    {
        if (! Gate::check('accessPlugin-bunting')) {
            return null;
        }

        $settings = $this->getSettings();
        $subnav = [new NavItem()->label(t('Flags', category: 'bunting'))->href(Url::cpUrl('bunting'))];

        if (Gate::check(self::PERMISSION_AUDIT)) {
            $subnav[] = new NavItem()->label(t('Audit log', category: 'bunting'))->href(Url::cpUrl('bunting/audit'));
        }

        return new NavItem()
            ->label($settings->navLabel() ?? $this->name)
            ->href('bunting')
            ->icon($settings->navIcon() ?? $this->cpNavIconPath())
            ->subnav($subnav);
    }

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        return Ui::make([
            Field::make(t('Make Bunting Pennant’s default store', category: 'bunting'), Lightswitch::make('defaultStore'))
                ->instructions(t('`Feature::active()`, Blade’s `@feature` and Twig’s `feature()` then read Bunting’s flags. Turn off if your app has its own Pennant store; use `Feature::store(\'bunting\')` and `bunting.active()` instead.', category: 'bunting')),
            Field::make(t('Percentage rollouts for guests', category: 'bunting'), Lightswitch::make('guestRollout'))
                ->instructions(t('Buckets signed-out visitors with a random first-party cookie (`bunting_visitor`, one year), so they can be part of a percentage rollout. Off: percentage rollouts reach signed-in users only. Check your cookie policy before turning this on.', category: 'bunting')),
            Field::make(t('Sidebar label', category: 'bunting'), Text::make('cpNavLabel')->placeholder('Bunting'))
                ->instructions(t('What the sidebar link says. Leave blank for “Bunting”.', category: 'bunting')),
            Field::make(t('Sidebar icon', category: 'bunting'), IconPicker::make('cpNavIcon')->sets('*'))
                ->instructions(t('One of Craft’s icons, or one from a custom icon set. Leave blank for Bunting’s own icon.', category: 'bunting')),
        ]);
    }
}
