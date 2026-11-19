<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Flags;

use DateTimeImmutable;

/**
 * A feature flag: its definition (project config) and its rollout (database).
 *
 * Rollout modes — for the whole flag, or per site:
 * - `off`:   off for everyone (except users with an override)
 * - `on`:    on for everyone
 * - `rules`: on for members of `groupUids`, then for `percentage`% of signed-in
 *            users (sticky: the same users stay in as the percentage grows)
 */
final class Flag
{
    public const string MODE_OFF = 'off';

    public const string MODE_ON = 'on';

    public const string MODE_RULES = 'rules';

    public const array MODES = [self::MODE_OFF, self::MODE_ON, self::MODE_RULES];

    /** Lowercase letters, digits, dashes, dots and underscores; starts with a letter. */
    public const string HANDLE_PATTERN = '/^[a-z][a-z0-9\-_.]{0,63}$/D';

    /**
     * @param  list<string>  $uriPatterns  Site URIs (`*` wildcards) that 404 while the flag is off.
     * @param  array<string, string>  $siteModes  Site UID → mode, overriding `$mode` on that site.
     * @param  list<string>  $groupUids  User groups the flag is on for in `rules` mode.
     */
    public function __construct(
        public ?int $id,
        public string $uid,
        public string $handle,
        public string $name,
        public ?string $description = null,
        public bool $public = false,
        public array $uriPatterns = [],
        public string $mode = self::MODE_OFF,
        public array $siteModes = [],
        public array $groupUids = [],
        public int $percentage = 0,
        public ?DateTimeImmutable $lastCheckedAt = null,
        public ?DateTimeImmutable $dateUpdated = null,
    ) {}

    /** The mode on a site: its own, or the flag's. */
    public function modeFor(string $siteUid): string
    {
        $mode = $this->siteModes[$siteUid] ?? null;

        return in_array($mode, self::MODES, true) ? $mode : $this->mode;
    }

    /** @return array{handle: string, name: string, description: string|null, public: bool, uriPatterns: list<string>} */
    public function definitionConfig(): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'description' => $this->description,
            'public' => $this->public,
            'uriPatterns' => $this->uriPatterns,
        ];
    }

    /** @return array{mode: string, siteModes: array<string, string>, groupUids: list<string>, percentage: int} */
    public function rollout(): array
    {
        return [
            'mode' => $this->mode,
            'siteModes' => $this->siteModes,
            'groupUids' => $this->groupUids,
            'percentage' => $this->percentage,
        ];
    }

    /** Whether a site-relative URI (no leading slash) is gated by this flag. */
    public function gatesUri(string $uri): bool
    {
        $uri = trim($uri, '/');

        foreach ($this->uriPatterns as $pattern) {
            $pattern = trim($pattern, '/');

            if ($pattern === '') {
                continue;
            }

            $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/iuD';

            if (preg_match($regex, $uri) === 1) {
                return true;
            }
        }

        return false;
    }
}
