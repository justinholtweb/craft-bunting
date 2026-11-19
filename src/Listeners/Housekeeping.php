<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Listeners;

use CraftCms\Cms\Element\Events\ElementDeleted;
use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Events\EditUserScreensResolving;
use CraftCms\Cms\User\Events\GroupsAndPermissionsAssigned;
use CraftCms\Cms\User\Events\UserAssignedToGroups;
use CraftCms\Cms\User\Events\UserGroupDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use JustinHoltWeb\Bunting\Flags\Flags;
use JustinHoltWeb\Bunting\Http\Controllers\FlagsController;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * Keeps stored values honest when what they were worked out from changes outside
 * Bunting (group membership, deleted users/sites/groups), and adds the “Feature
 * flags” screen to the user edit page.
 */
class Housekeeping
{
    public function __construct(private readonly Flags $flags) {}

    /** Group-targeted values were stored for the user's old groups. */
    public function groupsChanged(UserAssignedToGroups $event): void
    {
        $uid = DB::table('elements')->where('id', $event->userId)->value('uid');

        if (is_string($uid)) {
            $this->flags->purgeStoredForUser($uid);
        }
    }

    public function groupsAndPermissionsAssigned(GroupsAndPermissionsAssigned $event): void
    {
        $this->flags->purgeStoredForUser((string) $event->user->uid);
    }

    public function elementDeleted(ElementDeleted $event): void
    {
        // Overrides go with the user (foreign key); stored values are keyed by UID.
        if ($event->element instanceof User && $event->element->uid !== null) {
            $this->flags->purgeStoredForUser((string) $event->element->uid);
        }
    }

    public function siteDeleted(SiteDeleted $event): void
    {
        $uid = (string) $event->site->uid;

        foreach ($this->flags->all() as $flag) {
            if (array_key_exists($uid, $flag->siteModes)) {
                $siteModes = $flag->siteModes;
                unset($siteModes[$uid]);
                $this->flags->saveRollout($flag, ['siteModes' => $siteModes]);
            }
        }

        $this->flags->purgeStoredForSite($uid);
    }

    public function groupDeleted(UserGroupDeleted $event): void
    {
        $uid = (string) $event->userGroup->uid;

        foreach ($this->flags->all() as $flag) {
            if (in_array($uid, $flag->groupUids, true)) {
                $this->flags->saveRollout($flag, ['groupUids' => array_values(array_diff($flag->groupUids, [$uid]))]);
            }
        }
    }

    public function userScreens(EditUserScreensResolving $event): void
    {
        if ($event->editedUser->id !== null && Gate::check('accessPlugin-bunting') && FlagsController::canTargetUsers()) {
            $event->screens['bunting'] = [
                'label' => t('Feature flags', category: 'bunting'),
                'url' => cp_url('bunting/users/'.$event->editedUser->id),
            ];
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            UserAssignedToGroups::class => 'groupsChanged',
            GroupsAndPermissionsAssigned::class => 'groupsAndPermissionsAssigned',
            ElementDeleted::class => 'elementDeleted',
            SiteDeleted::class => 'siteDeleted',
            UserGroupDeleted::class => 'groupDeleted',
            EditUserScreensResolving::class => 'userScreens',
        ];
    }
}
