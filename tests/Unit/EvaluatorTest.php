<?php

declare(strict_types=1);

use JustinHoltWeb\Bunting\Flags\Evaluator;
use JustinHoltWeb\Bunting\Flags\Flag;
use JustinHoltWeb\Bunting\Pennant\Scope;

function flag(array $props = []): Flag
{
    return new Flag(...array_merge(['id' => 1, 'uid' => 'flag-uid', 'handle' => 'new-nav', 'name' => 'New nav'], $props));
}

function user(string $uid = 'user-1', array $groups = [], string $site = 'site-a'): Scope
{
    return new Scope(siteUid: $site, userId: 1, userUid: $uid, groupUids: $groups);
}

it('is off or on for everyone by mode', function () {
    expect(Evaluator::evaluate(flag(['mode' => 'off']), user()))->toBe(['value' => false, 'reason' => 'off'])
        ->and(Evaluator::evaluate(flag(['mode' => 'on']), user()))->toBe(['value' => true, 'reason' => 'on'])
        ->and(Evaluator::evaluate(flag(['mode' => 'on']), new Scope('site-a')))->toBe(['value' => true, 'reason' => 'on']);
});

it('lets a site mode beat the flag mode', function () {
    $flag = flag(['mode' => 'off', 'siteModes' => ['site-b' => 'on']]);

    expect(Evaluator::evaluate($flag, user(site: 'site-a'))['value'])->toBeFalse()
        ->and(Evaluator::evaluate($flag, user(site: 'site-b'))['value'])->toBeTrue()
        ->and(flag(['mode' => 'on', 'siteModes' => ['site-b' => 'off']])->modeFor('site-b'))->toBe('off');
});

it('ignores an invalid site mode', function () {
    expect(flag(['mode' => 'on', 'siteModes' => ['site-a' => 'maybe']])->modeFor('site-a'))->toBe('on');
});

it('targets user groups in rules mode', function () {
    $flag = flag(['mode' => 'rules', 'groupUids' => ['staff']]);

    expect(Evaluator::evaluate($flag, user(groups: ['staff', 'other'])))->toBe(['value' => true, 'reason' => 'group'])
        ->and(Evaluator::evaluate($flag, user(groups: ['other'])))->toBe(['value' => false, 'reason' => 'no-match'])
        ->and(Evaluator::evaluate(flag(['mode' => 'off', 'groupUids' => ['staff']]), user(groups: ['staff']))['value'])->toBeFalse();
});

it('lets an override beat every rule, but never for guests', function () {
    expect(Evaluator::evaluate(flag(['mode' => 'off']), user(), true))->toBe(['value' => true, 'reason' => 'override'])
        ->and(Evaluator::evaluate(flag(['mode' => 'on']), user(), false))->toBe(['value' => false, 'reason' => 'override'])
        ->and(Evaluator::evaluate(flag(['mode' => 'off']), new Scope('site-a'), true)['value'])->toBeFalse();
});

it('rolls out to roughly the percentage, stickily', function () {
    $in = fn (int $pct) => array_filter(range(1, 2000), fn ($i) => Evaluator::evaluate(flag(['mode' => 'rules', 'percentage' => $pct]), user("u{$i}"))['value']);

    $ten = $in(10);
    $fifty = $in(50);

    expect(count($ten))->toBeGreaterThan(140)->toBeLessThan(260)
        ->and(count($fifty))->toBeGreaterThan(900)->toBeLessThan(1100)
        // Raising the percentage only adds people.
        ->and(array_diff(array_keys($ten), array_keys($fifty)))->toBe([])
        ->and(count($in(0)))->toBe(0)
        ->and(count($in(100)))->toBe(2000);
});

it('buckets per flag, so different flags reach different people', function () {
    $a = array_map(fn ($i) => Evaluator::bucket('flag-a', "u{$i}"), range(1, 50));
    $b = array_map(fn ($i) => Evaluator::bucket('flag-b', "u{$i}"), range(1, 50));

    expect($a)->not->toBe($b)
        ->and(min($a))->toBeGreaterThanOrEqual(0)
        ->and(max($a))->toBeLessThan(10000)
        ->and(Evaluator::bucket('flag-a', 'u1'))->toBe(Evaluator::bucket('flag-a', 'u1'));
});

it('leaves guests out of percentage rollouts unless they carry a visitor id', function () {
    $flag = flag(['mode' => 'rules', 'percentage' => 100]);

    expect(Evaluator::evaluate($flag, new Scope('site-a'))['value'])->toBeFalse()
        ->and(Evaluator::evaluate($flag, new Scope('site-a', visitorId: str_repeat('a', 32)))['reason'])->toBe('rollout');
});
