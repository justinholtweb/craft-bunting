<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Listeners;

use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use JustinHoltWeb\Bunting\Flags\Flag;
use JustinHoltWeb\Bunting\Flags\Flags;
use JustinHoltWeb\Bunting\Gql\FlagsResolver;

use function CraftCms\Cms\t;

/**
 * One schema permission per *public* flag: “Read the “new-nav” flag”. Private
 * flags aren't offered, and a flag made private later drops out of every schema.
 */
class RegisterGqlComponents
{
    public function __construct(private readonly Flags $flags) {}

    public function handle(GqlSchemaComponentsResolving $event): void
    {
        $components = [];

        foreach (array_filter($this->flags->all(), fn (Flag $flag) => $flag->public) as $flag) {
            $components[FlagsResolver::component($flag).':read'] = [
                'label' => t('Read the “{handle}” flag', ['handle' => $flag->handle], category: 'bunting'),
                'info' => t('Adds the `buntingFlags` query: whether the flag is on for the requesting user (or a guest). Rules and targeting are never exposed.', category: 'bunting'),
            ];
        }

        if ($components !== []) {
            $event->queries[t('Bunting flags', category: 'bunting')] = $components;
        }
    }
}
