<?php

// Bunting harness: GraphQL schemas + tokens (idempotent). Token = "bunting{name}" padded to 32 chars.
//   public   the primary site + the public flag
//   private  the primary site + the public flag + (forced) the private flag's component
//   none     the primary site only
//   fr       buntingfr (if it exists) + the public flag
use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\Data\GqlToken;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Facades\Sites;
use JustinHoltWeb\Bunting\Flags\Flags;

require_once __DIR__.'/bunting-retry.php';

$flags = app(Flags::class);
$site = 'sites.'.Sites::getPrimarySite()->uid.':read';
$public = 'buntingFlags.'.$flags->get('bunting-public')->uid.':read';
$private = 'buntingFlags.'.$flags->get('bunting-private')->uid.':read';
$fr = Sites::getSiteByHandle('buntingfr', true);

$schemas = [
    'public' => [$site, $public],
    'private' => [$site, $public, $private],
    'none' => [$site],
    ...($fr ? ['fr' => ['sites.'.$fr->uid.':read', $public]] : []),
];

foreach ($schemas as $name => $scope) {
    $schema = collect(Gql::getSchemas())->first(fn ($s) => $s->name === "bunting_{$name}") ?? new GqlSchema;
    $schema->name = "bunting_{$name}";
    $schema->scope = $scope;
    bunting_retry(fn () => Gql::saveSchema($schema)) || throw new RuntimeException(json_encode($schema->errors()->all()));

    $token = collect(Gql::getTokens())->first(fn ($t) => $t->name === "bunting_{$name}") ?? new GqlToken;
    $token->name = "bunting_{$name}";
    $token->schemaId = $schema->id;
    $token->accessToken = str_pad("bunting{$name}", 32, '0');
    Gql::saveToken($token) || throw new RuntimeException(json_encode($token->errors()->all()));
}

Gql::invalidateCaches();
echo 'ok';
