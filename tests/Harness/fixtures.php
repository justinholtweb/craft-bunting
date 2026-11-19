<?php

// Bunting harness fixtures (idempotent). Users bunting_alice (staff), bunting_bob, bunting_carol
// (password "bunting-pass-1"), and seven flags. Prints the users' IDs and UIDs as JSON.
// Run with: ddev artisan tinker --execute "require 'storage/bunting-fixtures.php';"

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\UserGroups;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
use JustinHoltWeb\Bunting\Flags\Flag;
use JustinHoltWeb\Bunting\Flags\Flags;

require_once __DIR__.'/bunting-retry.php';

$staff = UserGroups::getGroupByHandle('staff') ?? throw new RuntimeException('The harness `staff` group is missing.');
$people = [];

foreach (['alice' => [$staff->id], 'bob' => [], 'carol' => []] as $name => $groups) {
    $username = "bunting_{$name}";
    $user = User::find()->username($username)->status(null)->one() ?? new User;
    $user->username = $username;
    $user->email = "{$username}@bunting.test";
    $user->fullName = ucfirst($name).' Bunting';
    $user->newPassword = 'bunting-pass-1';
    $user->active = true;
    $user->pending = false;
    Elements::saveElement($user) || throw new RuntimeException(json_encode($user->errors()->all()));
    Users::assignUserToGroups($user->id, $groups);
    $people[$name] = ['id' => $user->id, 'uid' => $user->uid];
}

$flags = app(Flags::class);
$definitions = [
    // handle => [name, rollout, public, uriPatterns]
    'bunting-nav' => ['New navigation', ['mode' => 'rules', 'groupUids' => [$staff->uid], 'percentage' => 0, 'siteModes' => []], false, []],
    'bunting-off' => ['Always off', ['mode' => 'off', 'siteModes' => [], 'groupUids' => [], 'percentage' => 0], false, []],
    'bunting-on' => ['Always on', ['mode' => 'on', 'siteModes' => [], 'groupUids' => [], 'percentage' => 0], false, []],
    'bunting-pct' => ['Half rollout', ['mode' => 'rules', 'percentage' => 50, 'groupUids' => [], 'siteModes' => []], false, []],
    'bunting-gate' => ['Gated pages', ['mode' => 'off', 'siteModes' => [], 'groupUids' => [], 'percentage' => 0], false, ['bunting/gated', 'bunting/beta/*']],
    'bunting-public' => ['Public flag', ['mode' => 'on', 'siteModes' => [], 'groupUids' => [], 'percentage' => 0], true, []],
    'bunting-private' => ['Private flag', ['mode' => 'on', 'siteModes' => [], 'groupUids' => [], 'percentage' => 0], false, []],
];

foreach ($definitions as $handle => [$name, $rollout, $public, $uris]) {
    $flag = $flags->get($handle) ?? new Flag(null, '', $handle, $name);
    $flag->name = $name;
    $flag->public = $public;
    $flag->uriPatterns = $uris;
    bunting_retry(fn () => $flags->saveDefinition($flag));
    $flags->saveRollout($flags->get($handle), $rollout);
}

// No leftover overrides from an earlier run.
foreach ($flags->all() as $flag) {
    foreach ($people as $p) {
        $flags->setOverride($flag, $p['id'], $p['uid'], null, 'fixture');
    }
}

echo json_encode($people);
