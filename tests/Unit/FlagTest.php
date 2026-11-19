<?php

declare(strict_types=1);

use JustinHoltWeb\Bunting\Flags\AuditLog;
use JustinHoltWeb\Bunting\Flags\Flag;
use JustinHoltWeb\Bunting\Flags\Flags;
use JustinHoltWeb\Bunting\Pennant\Scope;
use JustinHoltWeb\Bunting\Settings;

it('gates URIs by pattern, with * wildcards and no regex', function () {
    $flag = new Flag(1, 'u', 'beta', 'Beta', uriPatterns: ['/new-pricing/', 'beta/*', 'a.b']);

    expect($flag->gatesUri('new-pricing'))->toBeTrue()
        ->and($flag->gatesUri('/beta/x/y'))->toBeTrue()
        ->and($flag->gatesUri('beta'))->toBeFalse()
        ->and($flag->gatesUri('new-pricing/more'))->toBeFalse()
        ->and($flag->gatesUri('axb'))->toBeFalse()
        ->and($flag->gatesUri('a.b'))->toBeTrue()
        ->and((new Flag(1, 'u', 'x', 'X'))->gatesUri(''))->toBeFalse();
});

it('accepts only tidy handles', function (string $handle, bool $ok) {
    expect((bool) preg_match(Flag::HANDLE_PATTERN, $handle))->toBe($ok);
})->with([
    ['new-nav', true], ['checkout.v2', true], ['a_b', true],
    ['New-Nav', false], ['1nav', false], ['new nav', false], ['', false], [str_repeat('a', 65), false],
]);

it('serialises scopes per site and user, and guests per site', function () {
    expect((new Scope('site-a', 3, 'user-uid'))->featureScopeSerialize())->toBe('site:site-a|user:user-uid')
        ->and((new Scope('site-a'))->featureScopeSerialize())->toBe('site:site-a|guest')
        ->and((new Scope('site-a'))->isGuest())->toBeTrue();
});

it('normalises definitions from project config', function () {
    expect(Flags::normaliseDefinition(['handle' => 'x', 'description' => '  ', 'uriPatterns' => [' a ', '', 'b']]))->toBe([
        'handle' => 'x', 'name' => 'x', 'description' => null, 'public' => false, 'uriPatterns' => ['a', 'b'],
    ]);
});

it('diffs only what changed, ignoring key order', function () {
    expect(AuditLog::diff(
        ['mode' => 'off', 'siteModes' => ['a' => 'on', 'b' => 'off'], 'percentage' => 0],
        ['mode' => 'rules', 'siteModes' => ['b' => 'off', 'a' => 'on'], 'percentage' => 0],
    ))->toBe(['mode' => ['from' => 'off', 'to' => 'rules']]);
});

it('presents audit rows as readable lines', function () {
    $rows = AuditLog::present([
        ['action' => 'rollout', 'changes' => ['mode' => ['from' => 'off', 'to' => 'on'], 'groupUids' => ['from' => [], 'to' => ['g']]]],
        ['action' => 'override', 'changes' => ['user' => ['id' => 1, 'label' => 'ada'], 'override' => ['from' => null, 'to' => true]]],
    ]);

    expect($rows[0]['lines'])->toBe(['mode: off → on', 'groupUids: — → ["g"]'])
        ->and($rows[1]['lines'])->toBe(['ada: — → on']);
});

it('has safe defaults', function () {
    $settings = new Settings;

    expect($settings->defaultStore)->toBeTrue()
        ->and($settings->guestRollout)->toBeFalse()
        ->and($settings->navLabel())->toBeNull();
});
