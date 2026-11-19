<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Gql;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/** What `buntingFlags` returns per flag: its handle and whether it's on. Nothing else. */
class FlagType
{
    public static function getType(): ObjectType
    {
        return GqlEntityRegistry::getOrCreate('BuntingFlag', fn () => new ObjectType([
            'name' => 'BuntingFlag',
            'description' => 'A Bunting feature flag, resolved for this request.',
            'fields' => fn () => [
                'handle' => ['type' => Type::nonNull(Type::string())],
                'active' => ['type' => Type::nonNull(Type::boolean()), 'description' => 'Whether the flag is on for this request’s user (or guest) on the site.'],
            ],
        ]));
    }
}
