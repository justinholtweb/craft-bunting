<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\UserGroups;
use Illuminate\Console\Command;
use JustinHoltWeb\Bunting\Flags\Flags;

class ListCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:bunting:list';

    protected $description = 'Lists Bunting’s feature flags and their rollout';

    public function handle(Flags $flags): int
    {
        $rows = [];

        foreach ($flags->all() as $flag) {
            $sites = [];
            foreach ($flag->siteModes as $uid => $mode) {
                $sites[] = (rescue(fn () => Sites::getSiteByUid($uid, true)->handle, $uid, false)).'='.$mode;
            }

            $groups = array_map(fn (string $uid) => rescue(fn () => UserGroups::getGroupByUid($uid)->handle, $uid, false) ?? $uid, $flag->groupUids);

            $rows[] = [
                $flag->handle,
                $flag->mode,
                implode(', ', $sites),
                implode(', ', $groups),
                $flag->percentage.'%',
                $flag->public ? 'yes' : 'no',
                $flag->lastCheckedAt?->format('Y-m-d H:i') ?? 'never',
            ];
        }

        $this->table(['Flag', 'Mode', 'Sites', 'Groups', 'Rollout', 'Public', 'Last checked (UTC)'], $rows);

        return self::SUCCESS;
    }
}
