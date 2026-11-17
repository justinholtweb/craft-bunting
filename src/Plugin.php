<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Override;

use function CraftCms\Cms\t;

/**
 * Bunting — Feature flags for Craft on Laravel Pennant — flip them in the CP, scope them to users, groups, sites or a percentage.
 *
 * See PLAN.md for the build phases.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        return Ui::make([
            Field::make(t('Default scope', category: 'bunting'), Text::make('defaultScope'))
                ->instructions(t('user, group, site or global.', category: 'bunting')),
        ]);
    }
}
