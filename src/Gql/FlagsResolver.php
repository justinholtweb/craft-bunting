<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Gql;

use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use GraphQL\Error\UserError;
use JustinHoltWeb\Bunting\Bunting;
use JustinHoltWeb\Bunting\Flags\Flag;
use JustinHoltWeb\Bunting\Flags\Flags;

use function CraftCms\Cms\currentUserElement;

class FlagsResolver
{
    /** The schema component for a flag: `buntingFlags.<uid>:read`. */
    public static function component(Flag $flag): string
    {
        return 'buntingFlags.'.$flag->uid;
    }

    /** @return array<string, Flag> Public flags the active schema may read, by handle. */
    public static function allowedFlags(): array
    {
        return array_filter(
            app(Flags::class)->all(),
            fn (Flag $flag) => $flag->public && GqlHelper::canSchema(self::component($flag)),
        );
    }

    /**
     * @param  array<string, mixed>  $args
     * @return list<array{handle: string, active: bool}>
     */
    public static function resolve(mixed $source, array $args): array
    {
        $flags = self::allowedFlags();

        if (isset($args['handles'])) {
            $wanted = array_map('strval', (array) $args['handles']);
            $flags = array_filter($flags, fn (Flag $flag) => in_array($flag->handle, $wanted, true));
        }

        $site = self::site(isset($args['site']) ? (string) $args['site'] : null);
        $bunting = app(Bunting::class);
        $user = currentUserElement();
        $values = [];

        foreach ($flags as $flag) {
            $values[] = ['handle' => $flag->handle, 'active' => $bunting->activeFor($flag->handle, $user, $site)];
        }

        return $values;
    }

    /** The requested site, or the current one, if the schema can read it. */
    private static function site(?string $handle): Site
    {
        $allowed = GqlHelper::getAllowedSites();
        $handle ??= Sites::getCurrentSite()->handle;

        foreach ($allowed as $site) {
            if ($site->handle === $handle) {
                return $site;
            }
        }

        throw new UserError("This schema can’t read the “{$handle}” site.");
    }
}
