<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Pennant;

use JustinHoltWeb\Bunting\Bunting;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Contracts\FeatureScopeSerializeable;

/**
 * Who a flag is being checked for: a site, and a signed-in user or a guest.
 *
 * Pennant stores one resolved value per flag per scope (`site:<uid>|user:<uid>`).
 * Guests aren't stored. Build one with {@see Bunting::scope()};
 * pass it to Pennant with `Feature::store('bunting')->for($scope)`.
 */
final readonly class Scope implements FeatureScopeable, FeatureScopeSerializeable
{
    /**
     * @param  list<string>  $groupUids  The user's groups at the time the scope was built.
     * @param  bool  $current  Built for the current request (admin previews apply only then).
     */
    public function __construct(
        public string $siteUid,
        public ?int $userId = null,
        public ?string $userUid = null,
        public array $groupUids = [],
        public ?string $visitorId = null,
        public bool $current = false,
    ) {}

    public function isGuest(): bool
    {
        return $this->userUid === null;
    }

    public function toFeatureIdentifier(string $driver): mixed
    {
        return $this;
    }

    public function featureScopeSerialize(): string
    {
        return 'site:'.$this->siteUid.'|'.($this->userUid === null ? 'guest' : 'user:'.$this->userUid);
    }

    /** The string that buckets this scope into a percentage rollout, if it has one. */
    public function bucketKey(): ?string
    {
        return $this->userUid ?? ($this->visitorId !== null ? 'visitor:'.$this->visitorId : null);
    }
}
