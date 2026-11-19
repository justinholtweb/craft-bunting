<?php

// Bunting harness: Bunting's own second site, `buntingfr` (French), under /buntingfr/.
// Other agents share the harness: never touch a site this file didn't create. Idempotent.
// Run with: bin/harness-lock ddev artisan tinker --execute "require 'storage/bunting-multisite-fixtures.php';"

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;

require_once __DIR__.'/bunting-retry.php';

$fr = Sites::getSiteByHandle('buntingfr', true);

if (! $fr) {
    $primary = Sites::getPrimarySite();
    $fr = new Site;
    $fr->groupId = $primary->groupId;
    $fr->name = 'Bunting Français';
    $fr->handle = 'buntingfr';
    $fr->language = 'fr-FR';
    $fr->hasUrls = true;
    $fr->baseUrl = rtrim((string) $primary->getBaseUrl(), '/').'/buntingfr/';
    $fr->enabled = true;
    bunting_retry(fn () => Sites::saveSite($fr)) || throw new RuntimeException('site: '.json_encode($fr->errors()->all()));
}

echo json_encode(['id' => $fr->id, 'uid' => $fr->uid]);
