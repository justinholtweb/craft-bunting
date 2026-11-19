<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Pennant;

use JustinHoltWeb\Bunting\Flags\Evaluator;
use JustinHoltWeb\Bunting\Flags\Flag;
use JustinHoltWeb\Bunting\Flags\Flags;
use JustinHoltWeb\Bunting\Preview;
use Laravel\Pennant\Contracts\CanListStoredFeatures;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Drivers\DatabaseDriver;

/**
 * Pennant's `bunting` store: Pennant's database driver (on `bunting_features`)
 * with Bunting's flags defined in it.
 *
 * - A Bunting flag resolves through {@see Evaluator} for a {@see Scope}. Signed-in
 *   users' values are stored (Pennant's model); guests' are worked out per request.
 * - An admin's preview (`?bunting-preview=`) wins for their own request, and is never stored.
 * - Anything else — features the app defines with `Feature::define()` on this store,
 *   or unknown names (always off) — is plain Pennant.
 */
final class BuntingDriver implements CanListStoredFeatures, Driver
{
    /**
     * Flags, previews and scopes are resolved per call: they're request-scoped, and
     * Pennant keeps this driver for the life of the process (queue workers, Octane).
     */
    public function __construct(private readonly DatabaseDriver $database) {}

    public function define(string $feature, callable $resolver): void
    {
        $this->database->define($feature, $resolver);
    }

    public function defined(): array
    {
        return array_values(array_unique([...array_keys($this->flags()->all()), ...$this->database->defined()]));
    }

    public function stored(): array
    {
        return $this->database->stored();
    }

    public function getAll(array $features): array
    {
        $results = [];

        foreach ($features as $feature => $scopes) {
            foreach ($scopes as $index => $scope) {
                $results[$feature][$index] = $this->get($feature, $scope);
            }
        }

        return $results;
    }

    public function get(string $feature, mixed $scope): mixed
    {
        $flag = $this->flags()->get($feature);

        if ($flag === null) {
            return $this->database->get($feature, $scope);
        }

        $scope = app(ScopeFactory::class)->normalise($scope);

        // Fails closed: a Bunting flag checked for something that isn't a site/user scope is off.
        if ($scope === null) {
            return false;
        }

        $this->flags()->touch($flag);

        if ($scope->current && ($preview = app(Preview::class)->value($flag->handle)) !== null) {
            return $preview;
        }

        if ($scope->isGuest()) {
            return Evaluator::evaluate($flag, $scope)['value'];
        }

        $this->ensureDefined($flag);

        return $this->database->get($feature, $scope);
    }

    public function set(string $feature, mixed $scope, mixed $value): void
    {
        $this->database->set($feature, $this->scopeFor($feature, $scope), $value);
    }

    public function setForAllScopes(string $feature, mixed $value): void
    {
        $this->database->setForAllScopes($feature, $value);
    }

    public function delete(string $feature, mixed $scope): void
    {
        $this->database->delete($feature, $this->scopeFor($feature, $scope));
    }

    /** @param  list<string>|null  $features */
    public function purge(?array $features): void
    {
        $this->database->purge($features);
    }

    /** Bunting flags are stored under Bunting scopes; app-defined features keep theirs. */
    private function scopeFor(string $feature, mixed $scope): mixed
    {
        return $this->flags()->get($feature) !== null ? (app(ScopeFactory::class)->normalise($scope) ?? $scope) : $scope;
    }

    private function flags(): Flags
    {
        return app(Flags::class);
    }

    /** Registers the flag's resolver with the database driver, which stores what it returns. */
    private function ensureDefined(Flag $flag): void
    {
        $flags = $this->flags();
        $handle = $flag->handle;

        $this->database->define($handle, function (mixed $scope) use ($flags, $handle): bool {
            $flag = $flags->get($handle);

            if ($flag === null || ! $scope instanceof Scope || $scope->userId === null) {
                return false;
            }

            return Evaluator::evaluate($flag, $scope, $flags->override($flag, $scope->userId))['value'];
        });
    }
}
