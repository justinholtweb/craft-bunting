<?php

// Removes `buntingfr` (and only it). Run under bin/harness-lock.

use CraftCms\Cms\Support\Facades\Sites;

require_once __DIR__.'/bunting-retry.php';

$fr = Sites::getSiteByHandle('buntingfr', true);
echo $fr ? (bunting_retry(fn () => Sites::deleteSite($fr)) ? 'removed' : 'failed') : 'absent';
