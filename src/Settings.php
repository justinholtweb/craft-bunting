<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting;

use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 */
class Settings extends PluginSettings
{
    /** Default scope. user, group, site or global. */
    public ?string $defaultScope = 'user';
}
