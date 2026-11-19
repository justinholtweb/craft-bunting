<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Gql;

use CraftCms\Cms\Gql\Queries\Query;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\Type;

/**
 * `buntingFlags` — resolved values of *public* flags for the request's scope (the
 * token's or session's user, else a guest) on a site the schema can read. Only
 * flags ticked for the schema are returned; rules, groups, percentages and users
 * never are.
 *
 * @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition
 */
class FlagsQuery extends Query
{
    /** @return array<string, UnnamedFieldDefinitionConfig> */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && FlagsResolver::allowedFlags() === []) {
            return [];
        }

        return [
            'buntingFlags' => [
                'type' => Type::nonNull(Type::listOf(Type::nonNull(FlagType::getType()))),
                'args' => [
                    'handles' => ['name' => 'handles', 'type' => Type::listOf(Type::nonNull(Type::string())), 'description' => 'Only these flags. Flags the schema can’t read are left out, as are unknown handles.'],
                    'site' => ['name' => 'site', 'type' => Type::string(), 'description' => 'A site handle (default: the current site, if the schema can read it).'],
                ],
                'resolve' => [FlagsResolver::class, 'resolve'],
                'description' => 'Bunting feature flags (public ones this schema can read), resolved for the current user or guest.',
            ],
        ];
    }
}
