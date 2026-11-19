<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Listeners;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Gql\Events\ExecutedGqlQuery;
use CraftCms\Cms\Gql\Events\GqlQueryExecuting;

/**
 * Craft caches GraphQL results per schema and query, but a flag's value depends on
 * who's asking and on rules that change outside Craft's cache tags. A query that
 * reads `buntingFlags` is therefore executed fresh and never cached.
 */
class KeepFlagsOutOfGqlCache
{
    private ?bool $previous = null;

    public function executing(GqlQueryExecuting $event): void
    {
        if (stripos($event->query, 'buntingFlags') === false) {
            return;
        }

        $config = Cms::config();
        $this->previous = $config->enableGraphqlCaching;
        $config->enableGraphqlCaching = false;
    }

    public function executed(ExecutedGqlQuery $event): void
    {
        if ($this->previous !== null) {
            Cms::config()->enableGraphqlCaching = $this->previous;
            $this->previous = null;
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            GqlQueryExecuting::class => 'executing',
            ExecutedGqlQuery::class => 'executed',
        ];
    }
}
