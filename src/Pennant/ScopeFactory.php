<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Pennant;

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Data\UserGroup;
use CraftCms\Cms\User\Elements\User;
use InvalidArgumentException;
use JustinHoltWeb\Bunting\Settings;
use Throwable;

use function CraftCms\Cms\currentUserElement;

/**
 * Builds {@see Scope}s: for the current request (the signed-in user, or a guest,
 * on the current site), or for any user and site.
 */
final class ScopeFactory
{
    public const string VISITOR_COOKIE = 'bunting_visitor';

    public function __construct(private readonly Settings $settings) {}

    public function current(): Scope
    {
        return $this->make(currentUserElement(), Sites::getCurrentSite(), current: true);
    }

    /**
     * @param  User|int|null  $user  A user, user ID, or null for a guest.
     * @param  Site|string|null  $site  A site, site handle, or null for the current site.
     *
     * @throws InvalidArgumentException for an unknown user ID or site handle.
     */
    public function for(User|int|null $user = null, Site|string|null $site = null): Scope
    {
        if (is_int($user)) {
            $user = User::find()->id($user)->status(null)->one()
                ?? throw new InvalidArgumentException("No user with ID {$user}.");
        }

        if (is_string($site)) {
            $site = Sites::getSiteByHandle($site, false)
                ?? throw new InvalidArgumentException("No site with the handle “{$site}”.");
        }

        return $this->make($user instanceof User ? $user : null, $site ?? Sites::getCurrentSite());
    }

    /** Pennant hands drivers whatever scope it was given; Bunting understands these. */
    public function normalise(mixed $scope): ?Scope
    {
        return match (true) {
            $scope instanceof Scope => $scope,
            $scope instanceof User => $this->make($scope, Sites::getCurrentSite()),
            $scope === null => $this->make(null, Sites::getCurrentSite()),
            default => null,
        };
    }

    private function make(?User $user, Site $site, bool $current = false): Scope
    {
        $groups = $user ? array_values(array_map(fn (UserGroup $g): string => (string) $g->uid, $user->getGroups())) : [];

        return new Scope(
            siteUid: (string) $site->uid,
            userId: $user?->id,
            userUid: $user?->uid,
            groupUids: $groups,
            visitorId: $user === null && $current ? $this->visitorId() : null,
            current: $current,
        );
    }

    /** The anonymous visitor's ID, only when guest rollouts are switched on. */
    private function visitorId(): ?string
    {
        if (! $this->settings->guestRollout) {
            return null;
        }

        try {
            $id = request()->cookies->get(self::VISITOR_COOKIE);
        } catch (Throwable) {
            return null;
        }

        return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) ? $id : null;
    }
}
