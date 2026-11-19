<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting;

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\User\Elements\User;
use InvalidArgumentException;
use JustinHoltWeb\Bunting\Flags\Evaluator;
use JustinHoltWeb\Bunting\Flags\Flag;
use JustinHoltWeb\Bunting\Flags\Flags;
use JustinHoltWeb\Bunting\Pennant\Scope;
use JustinHoltWeb\Bunting\Pennant\ScopeFactory;
use Laravel\Pennant\Drivers\Decorator;
use Laravel\Pennant\FeatureManager;

/**
 * The template API — `bunting` in Twig, `$bunting` in Blade — and the PHP one.
 *
 * ```twig
 * {% if bunting.active('new-nav') %}…{% endif %}
 * {% if bunting.activeFor('new-nav', currentUser, 'fr') %}…{% endif %}
 * ```
 */
final class Bunting
{
    public const string STORE = 'bunting';

    public function __construct(
        private readonly Flags $flags,
        private readonly ScopeFactory $scopes,
        private readonly FeatureManager $features,
    ) {}

    /** Whether a flag is on for the current request (signed-in user or guest, current site). */
    public function active(string $handle): bool
    {
        return $this->store()->for($this->scopes->current())->active($handle);
    }

    public function inactive(string $handle): bool
    {
        return ! $this->active($handle);
    }

    /** Whether every one of the flags is on. */
    public function allActive(string ...$handles): bool
    {
        return array_all($handles, fn (string $h) => $this->active($h));
    }

    /** Whether any of the flags is on. */
    public function someActive(string ...$handles): bool
    {
        return array_any($handles, fn (string $h) => $this->active($h));
    }

    /**
     * Whether a flag is on for a user (or a guest) on a site. Unknown users and
     * site handles count as off rather than erroring in a template.
     */
    public function activeFor(string $handle, User|int|null $user = null, Site|string|null $site = null): bool
    {
        try {
            $scope = $this->scopes->for($user, $site);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $this->store()->for($scope)->active($handle);
    }

    /**
     * Pennant scope for app code: `Feature::store('bunting')->for(Bunting::scope($user, 'fr'))`.
     *
     * @throws InvalidArgumentException for an unknown user ID or site handle.
     */
    public function scope(User|int|null $user = null, Site|string|null $site = null): Scope
    {
        return $this->scopes->for($user, $site);
    }

    /** @return array<string, bool> Every Bunting flag's value for the current request. */
    public function all(): array
    {
        $scope = $this->scopes->current();
        $values = [];

        foreach ($this->flags->all() as $handle => $flag) {
            $values[$handle] = $this->store()->for($scope)->active($handle);
        }

        return $values;
    }

    /** 404s unless the flag is on — `{% requireFeature %}`, `@requireFeature`, the `feature` middleware. */
    public function requireFeature(string ...$handles): void
    {
        // Naming no flag fails closed.
        if ($handles === []) {
            abort(404);
        }

        foreach ($handles as $handle) {
            if (! $this->active($handle)) {
                abort(404);
            }
        }
    }

    /**
     * Why a flag has its value for a scope — the CP's “check a user” tool.
     *
     * @return array{value: bool, reason: string}|null
     */
    public function explain(string $handle, Scope $scope): ?array
    {
        $flag = $this->flags->get($handle);

        if (! $flag instanceof Flag) {
            return null;
        }

        return Evaluator::evaluate($flag, $scope, $scope->userId === null ? null : $this->flags->override($flag, $scope->userId));
    }

    private function store(): Decorator
    {
        return $this->features->store(self::STORE);
    }
}
