<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Http\Controllers;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\EditUserScreens;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Http\Request;
use JustinHoltWeb\Bunting\Bunting;
use JustinHoltWeb\Bunting\Flags\Flags;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Users → (a user) → Feature flags: every flag's value for this person on each
 * site, and their per-user overrides.
 */
class UserFlagsController
{
    use RespondsWithFlash;

    public function __construct(private readonly Flags $flags) {}

    public function show(int $userId, Bunting $bunting): CpScreenResponse
    {
        abort_unless(FlagsController::canTargetUsers(), 403);
        $user = User::find()->id($userId)->status(null)->one() ?? abort(404);
        $sites = Sites::getAllSites(true)->values()->all();
        $rows = [];

        foreach ($this->flags->all() as $flag) {
            $values = [];
            foreach ($sites as $site) {
                $values[$site->handle] = $bunting->explain($flag->handle, $bunting->scope($user, $site));
            }

            $rows[] = ['flag' => $flag, 'override' => $this->flags->override($flag, $userId), 'values' => $values];
        }

        $screens = app(EditUserScreens::class);
        $title = t('Feature flags', category: 'bunting');

        return new CpScreenResponse()
            ->title((string) $user->username)
            ->crumbs([
                new ActionItem()->label(t('Users', category: 'bunting'))->href(Url::cpUrl('users')),
                new ActionItem()->label((string) $user->username)->href((string) $user->getCpEditUrl()),
                new ActionItem()->label($title),
            ])
            ->subnav($screens->subnav($user, 'bunting'))
            ->contentTemplate('bunting/_user', [
                'user' => $user,
                'rows' => $rows,
                'sites' => $sites,
                'canEdit' => FlagsController::canEdit(),
                'reasons' => FlagsController::reasonLabels(),
            ]);
    }

    public function save(Request $request): Response
    {
        abort_unless(FlagsController::canTargetUsers(), 403);
        $user = User::find()->id($request->integer('userId'))->status(null)->one() ?? abort(404);
        $changed = 0;

        foreach ((array) $request->input('overrides', []) as $uid => $value) {
            $flag = $this->flags->getByUid((string) $uid);

            if ($flag === null || ! in_array($value, ['inherit', 'on', 'off'], true)) {
                continue;
            }

            $changed += (int) $this->flags->setOverride($flag, (int) $user->id, (string) $user->uid, $value === 'inherit' ? null : $value === 'on', (string) $user->username);
        }

        return $this->asSuccess(
            $changed > 0 ? t('Overrides saved.', category: 'bunting') : t('Nothing changed.', category: 'bunting'),
            redirect: Url::cpUrl('bunting/users/'.$user->id),
        );
    }
}
