<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Flags;

use JustinHoltWeb\Bunting\Pennant\Scope;

/**
 * The rules, as a pure function. Order:
 *
 * 1. a per-user override (on or off), on every site;
 * 2. the site's mode, or the flag's: `off` → off, `on` → on;
 * 3. `rules`: on for members of the flag's user groups, then for the flag's
 *    percentage of signed-in users (and of cookie-tagged visitors, if enabled);
 * 4. otherwise off.
 */
final class Evaluator
{
    public const string REASON_OVERRIDE = 'override';

    public const string REASON_OFF = 'off';

    public const string REASON_ON = 'on';

    public const string REASON_GROUP = 'group';

    public const string REASON_ROLLOUT = 'rollout';

    public const string REASON_NO_MATCH = 'no-match';

    public const string REASON_PREVIEW = 'preview';

    /** @return array{value: bool, reason: string} */
    public static function evaluate(Flag $flag, Scope $scope, ?bool $override = null): array
    {
        if ($override !== null && ! $scope->isGuest()) {
            return ['value' => $override, 'reason' => self::REASON_OVERRIDE];
        }

        $mode = $flag->modeFor($scope->siteUid);

        if ($mode === Flag::MODE_ON) {
            return ['value' => true, 'reason' => self::REASON_ON];
        }

        if ($mode !== Flag::MODE_RULES) {
            return ['value' => false, 'reason' => self::REASON_OFF];
        }

        if ($scope->groupUids !== [] && array_intersect($flag->groupUids, $scope->groupUids) !== []) {
            return ['value' => true, 'reason' => self::REASON_GROUP];
        }

        $key = $scope->bucketKey();

        if ($key !== null && $flag->percentage > 0 && self::bucket($flag->uid, $key) < $flag->percentage * 100) {
            return ['value' => true, 'reason' => self::REASON_ROLLOUT];
        }

        return ['value' => false, 'reason' => self::REASON_NO_MATCH];
    }

    /**
     * 0–9999, fixed per flag and subject. Keyed on the flag's UID (not its handle),
     * so renaming a flag doesn't reshuffle who's in; raising the percentage only adds people.
     */
    public static function bucket(string $flagUid, string $subject): int
    {
        return (int) (hexdec(substr(hash('sha256', $flagUid.'|'.$subject), 0, 8)) % 10000);
    }
}
