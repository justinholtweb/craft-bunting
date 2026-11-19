<?php

// Removes every Bunting harness fixture: bunting-* flags (and their audit rows and
// stored values), bunting_* users, bunting_* GraphQL schemas and tokens.
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Support\Facades\DB;
use JustinHoltWeb\Bunting\Flags\Flags;

require_once __DIR__.'/bunting-retry.php';

$flags = app(Flags::class);
foreach ($flags->all() as $handle => $flag) {
    if (str_starts_with($handle, 'bunting-')) {
        bunting_retry(fn () => $flags->deleteDefinition($flag));
    }
}
DB::table('bunting_audit')->where('flagHandle', 'like', 'bunting-%')->delete();
DB::table('bunting_features')->where('name', 'like', 'bunting-%')->delete();

foreach (User::find()->username(['bunting_alice', 'bunting_bob', 'bunting_carol', 'bunting_dave'])->status(null)->all() as $user) {
    Elements::deleteElement($user, true);
}

foreach (Gql::getTokens() as $t) {
    if (str_starts_with((string) $t->name, 'bunting_')) {
        Gql::deleteTokenById($t->id);
    }
}
foreach (Gql::getSchemas() as $s) {
    if (str_starts_with((string) $s->name, 'bunting_')) {
        Gql::deleteSchemaById($s->id);
    }
}

echo 'clean';
