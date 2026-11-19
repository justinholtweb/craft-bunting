<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting;

use CraftCms\Cms\Cp\Icons;
use CraftCms\Cms\Cp\IconSets;
use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 *
 * Flags themselves aren't settings: they're managed in the CP (Bunting section).
 */
class Settings extends PluginSettings
{
    /**
     * Make `bunting` Pennant's default store, so `Feature::active()`, Blade's
     * `@feature` and Twig's `feature()` read Bunting's flags. Off: use
     * `Feature::store('bunting')` / `bunting.active()` and keep your own default store.
     */
    public bool $defaultStore = true;

    /**
     * Percentage rollouts for guests too, bucketed by a first-party `bunting_visitor`
     * cookie (random, no personal data). Off: percentage rollouts reach signed-in users only.
     */
    public bool $guestRollout = false;

    /** What the sidebar link says. Null uses “Bunting”. */
    public ?string $cpNavLabel = null;

    /**
     * The sidebar icon: a Craft icon name (`flag`) or a custom icon set icon (`set:name`),
     * as the icon picker saves it. Null uses Bunting's own icon.
     */
    public ?string $cpNavIcon = null;

    /** The sidebar label, or null for the plugin's name. */
    public function navLabel(): ?string
    {
        $label = trim((string) $this->cpNavLabel);

        return $label === '' ? null : $label;
    }

    /** The sidebar icon if it names a real Craft or icon-set icon, else null. */
    public function navIcon(): ?string
    {
        $icon = trim((string) $this->cpNavIcon);

        return $icon !== '' && self::isIcon($icon) ? $icon : null;
    }

    public static function isIcon(string $icon): bool
    {
        if ($custom = IconSets::parse($icon)) {
            $svg = rescue(fn () => app(IconSets::class)->getIconSetByHandle($custom[0])?->getSvg($custom[1]), null, false);

            return is_string($svg) && stripos($svg, '<svg') !== false;
        }

        return (bool) preg_match('/^[\da-z\-]+$/D', $icon) && is_file(Icons::resolveIconPath($icon));
    }

    /**
     * @return array<string, list<string>>
     */
    public function getRules(): array
    {
        return [
            'cpNavLabel' => ['nullable', 'string', 'max:100'],
            'cpNavIcon' => ['nullable', 'string', 'max:100', 'regex:/^([a-zA-Z][\w\-]*:)?[\w\-]+$/'],
        ];
    }
}
