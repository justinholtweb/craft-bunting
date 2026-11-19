<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Http\Controllers;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\UserGroups;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;
use JustinHoltWeb\Bunting\Bunting;
use JustinHoltWeb\Bunting\Flags\AuditLog;
use JustinHoltWeb\Bunting\Flags\Flag;
use JustinHoltWeb\Bunting\Flags\Flags;
use JustinHoltWeb\Bunting\Plugin;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Bunting → Flags: the list, the edit screen (definition, rollout, overrides,
 * a “check a user” tool, recent changes), and the actions behind it.
 */
class FlagsController
{
    use RespondsWithFlash;

    public const array SITE_MODES = ['inherit', ...Flag::MODES];

    public function __construct(private readonly Flags $flags) {}

    public function index(): CpScreenResponse
    {
        $canCreate = self::canEdit() && self::canDefine();
        $table = Table::make('bunting-flags')
            ->columns([
                ['key' => 'name', 'label' => t('Name', category: 'bunting')],
                ['key' => 'handle', 'label' => t('Handle', category: 'bunting')],
                ['key' => 'rollout', 'label' => t('Rollout', category: 'bunting')],
                ['key' => 'sites', 'label' => t('Per site', category: 'bunting')],
                ['key' => 'public', 'label' => t('GraphQL', category: 'bunting')],
                ['key' => 'checked', 'label' => t('Last checked', category: 'bunting')],
            ])
            ->rows(array_values(array_map(fn (Flag $flag): array => [
                'id' => $flag->id,
                'name' => ['label' => $flag->name, 'url' => Url::cpUrl('bunting/flags/'.$flag->handle)],
                'handle' => ['html' => Html::tag('code', Html::encode($flag->handle))],
                'rollout' => self::rolloutSummary($flag->mode, $flag),
                'sites' => self::siteSummary($flag),
                'public' => $flag->public ? t('Public', category: 'bunting') : '—',
                'checked' => $flag->lastCheckedAt === null ? t('Never', category: 'bunting') : $flag->lastCheckedAt->format('Y-m-d H:i').' UTC',
            ], $this->flags->all())))
            ->emptyMessage(t('No flags yet.', category: 'bunting'))
            ->when($canCreate, fn (Table $table) => $table
                ->createAction(t('New flag', category: 'bunting'), Url::cpUrl('bunting/new'))
                ->createActionInPageHeader());

        return new CpScreenResponse()
            ->title(t('Feature flags', category: 'bunting'))
            ->ui(Ui::make([$table]));
    }

    public function create(Request $request): CpScreenResponse
    {
        abort_unless(self::canDefine(), 403, 'Flags can’t be created while admin changes are disallowed.');

        return $this->screen($request, new Flag(null, '', '', ''));
    }

    public function edit(Request $request, string $handle): CpScreenResponse
    {
        $flag = $this->flags->get($handle) ?? abort(404);

        return $this->screen($request, $flag);
    }

    public function save(Request $request): Response
    {
        $uid = (string) $request->input('uid', '');
        $flag = $uid === '' ? null : ($this->flags->getByUid($uid) ?? abort(404));
        $canDefine = self::canDefine();

        abort_if($flag === null && ! $canDefine, 403);

        $sites = Sites::getAllSites(true)->pluck('uid')->map(fn ($u) => (string) $u)->all();
        $groups = array_map(fn ($g) => (string) $g->uid, UserGroups::getAllGroups()->all());

        $rules = [
            'mode' => ['required', Rule::in(Flag::MODES)],
            'percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['string', Rule::in($groups)],
            'siteModes' => ['nullable', 'array'],
            'siteModes.*' => ['string', Rule::in(self::SITE_MODES)],
        ];

        if ($canDefine) {
            $rules += [
                'name' => ['required', 'string', 'max:255'],
                'handle' => ['required', 'string', 'regex:'.Flag::HANDLE_PATTERN, function (string $attribute, mixed $value, \Closure $fail) use ($flag) {
                    $other = $this->flags->get((string) $value);
                    if ($other !== null && $other->uid !== $flag?->uid) {
                        $fail(t('Another flag already uses this handle.', category: 'bunting'));
                    }
                }],
                'description' => ['nullable', 'string', 'max:2000'],
                'uriPatterns' => ['nullable', 'string', 'max:5000', function (string $attribute, mixed $value, \Closure $fail) {
                    foreach (self::lines((string) $value) as $line) {
                        if (! preg_match('#^[A-Za-z0-9\-_.~/*%]{1,255}$#D', $line)) {
                            $fail(t('URI patterns may only use letters, numbers, - _ . ~ / % and * (one per line).', category: 'bunting'));

                            return;
                        }
                    }
                }],
            ];
        }

        $validator = Validator::make($request->all(), $rules, [], [
            'handle' => t('Handle', category: 'bunting'),
            'name' => t('Name', category: 'bunting'),
            'percentage' => t('Percentage', category: 'bunting'),
        ]);

        if ($validator->fails()) {
            return $this->asFailure(t('Couldn’t save the flag.', category: 'bunting'), ['errors' => $validator->errors()->toArray()]);
        }

        $siteModes = [];
        foreach ((array) $request->input('siteModes', []) as $siteUid => $mode) {
            if (in_array($siteUid, $sites, true) && $mode !== 'inherit') {
                $siteModes[(string) $siteUid] = (string) $mode;
            }
        }

        if ($canDefine) {
            $definition = new Flag(
                id: $flag?->id,
                uid: $flag->uid ?? '',
                handle: (string) $request->input('handle'),
                name: trim((string) $request->input('name')),
                description: trim((string) $request->input('description')) ?: null,
                public: $request->boolean('public'),
                uriPatterns: self::lines((string) $request->input('uriPatterns')),
            );

            try {
                $this->flags->saveDefinition($definition);
            } catch (Throwable $e) {
                report($e);

                return $this->asFailure(t('Couldn’t save the flag.', category: 'bunting'));
            }

            $flag = $this->flags->getByUid($definition->uid) ?? abort(500, 'The flag wasn’t saved.');
        }

        /** @var Flag $flag */
        $this->flags->saveRollout($flag, [
            'mode' => (string) $request->input('mode'),
            'percentage' => (int) $request->input('percentage', 0),
            'groupUids' => array_values(array_unique(array_map('strval', (array) $request->input('groups', [])))),
            'siteModes' => $siteModes,
        ]);

        return $this->asSuccess(t('Flag saved.', category: 'bunting'), redirect: Url::cpUrl('bunting/flags/'.$flag->handle));
    }

    public function delete(Request $request): Response
    {
        abort_unless(self::canDefine(), 403, 'Flags can’t be deleted while admin changes are disallowed.');
        $flag = $this->flags->getByUid((string) $request->input('uid')) ?? abort(404);
        $this->flags->deleteDefinition($flag);

        return $this->asSuccess(t('Flag deleted.', category: 'bunting'), redirect: Url::cpUrl('bunting'));
    }

    public function purge(Request $request, AuditLog $audit): Response
    {
        $flag = $this->flags->getByUid((string) $request->input('uid')) ?? abort(404);
        $this->flags->purgeStored($flag->handle);
        $audit->record(AuditLog::PURGED, $flag->handle, $flag->uid);

        return $this->asSuccess(t('Stored values purged.', category: 'bunting'), redirect: Url::cpUrl('bunting/flags/'.$flag->handle));
    }

    /** Adds, changes or removes one user's override from the flag screen. */
    public function override(Request $request): Response
    {
        $flag = $this->flags->getByUid((string) $request->input('uid')) ?? abort(404);
        $value = (string) $request->input('value');
        abort_unless(in_array($value, ['on', 'off', 'remove'], true), 400);

        $user = self::findUser((string) $request->input('user'));

        if ($user === null) {
            return $this->asFailure(t('No user matches that username, email or ID.', category: 'bunting'));
        }

        $this->flags->setOverride($flag, (int) $user->id, (string) $user->uid, $value === 'remove' ? null : $value === 'on', (string) $user->username);

        return $this->asSuccess(t('Override saved.', category: 'bunting'), redirect: Url::cpUrl('bunting/flags/'.$flag->handle));
    }

    private function screen(Request $request, Flag $flag): CpScreenResponse
    {
        $isNew = $flag->id === null;
        $title = $isNew ? t('New flag', category: 'bunting') : $flag->name;
        $sites = Sites::getAllSites(true)->values()->all();
        $overrides = [];

        foreach ($this->flags->overrides($flag) as $userId => $value) {
            $user = User::find()->id($userId)->status(null)->one();
            $overrides[] = ['id' => $userId, 'label' => $user ? (string) $user->username : '#'.$userId, 'url' => $user?->getCpEditUrl(), 'value' => $value];
        }

        return new CpScreenResponse()
            ->title($title)
            ->crumbs([
                new ActionItem()->label(t('Feature flags', category: 'bunting'))->href(Url::cpUrl('bunting')),
                new ActionItem()->label($title),
            ])
            ->contentTemplate('bunting/_edit', [
                'flag' => $flag,
                'isNew' => $isNew,
                'sites' => $sites,
                'groups' => UserGroups::getAllGroups()->all(),
                'overrides' => $overrides,
                'canEdit' => self::canEdit(),
                'canDefine' => self::canDefine() && self::canEdit(),
                'check' => $isNew ? null : $this->check($request, $flag),
                'checkUser' => mb_substr(trim((string) $request->query('checkUser', '')), 0, 255),
                'audit' => $isNew || ! Gate::check(Plugin::PERMISSION_AUDIT) ? [] : AuditLog::present(app(AuditLog::class)->recent($flag->handle, 10)),
                'errors' => array_map(fn (array $messages) => (string) ($messages[0] ?? ''), $this->sessionErrors($request)),
                'form' => $this->formValues($request, $flag),
                'modes' => self::modeLabels(),
                'reasons' => self::reasonLabels(),
            ]);
    }

    /** @return array<string, list<string>> */
    private function sessionErrors(Request $request): array
    {
        $bag = $request->hasSession() ? $request->session()->get('errors') : null;

        return $bag instanceof ViewErrorBag ? $bag->getBag('default')->toArray() : [];
    }

    /**
     * The form's values: what was just submitted (after a failed save), else the flag's.
     *
     * @return array<string, mixed>
     */
    private function formValues(Request $request, Flag $flag): array
    {
        $old = $request->hasSession() ? (array) $request->session()->getOldInput() : [];
        $values = [
            'name' => $flag->name,
            'handle' => $flag->handle,
            'description' => (string) $flag->description,
            'public' => $flag->public,
            'uriPatterns' => implode("\n", $flag->uriPatterns),
            'mode' => $flag->mode,
            'percentage' => $flag->percentage,
            'siteModes' => $flag->siteModes,
            'groups' => $flag->groupUids,
        ];

        if ($old === []) {
            return $values;
        }

        foreach (['name', 'handle', 'description', 'uriPatterns', 'mode', 'percentage'] as $key) {
            $values[$key] = is_scalar($old[$key] ?? null) ? (string) $old[$key] : $values[$key];
        }

        $values['public'] = ! empty($old['public']);
        $values['siteModes'] = is_array($old['siteModes'] ?? null) ? $old['siteModes'] : [];
        $values['groups'] = is_array($old['groups'] ?? null) ? array_values($old['groups']) : [];

        return $values;
    }

    /**
     * “Check a user”: the flag's value and the rule that decided it.
     *
     * @return array{user: string, site: string, value: bool, reason: string}|array{error: string}|null
     */
    private function check(Request $request, Flag $flag): ?array
    {
        $who = trim((string) $request->query('checkUser', ''));
        $siteHandle = (string) $request->query('checkSite', '');

        if ($who === '' && $siteHandle === '') {
            return null;
        }

        $user = $who === '' ? null : self::findUser($who);

        if ($who !== '' && $user === null) {
            return ['error' => t('No user matches that username, email or ID.', category: 'bunting')];
        }

        try {
            $scope = app(Bunting::class)->scope($user, $siteHandle === '' ? null : $siteHandle);
        } catch (Throwable) {
            return ['error' => t('Unknown site.', category: 'bunting')];
        }

        $result = app(Bunting::class)->explain($flag->handle, $scope) ?? ['value' => false, 'reason' => 'off'];

        return [
            'user' => $user ? (string) $user->username : t('A guest', category: 'bunting'),
            'site' => $siteHandle === '' ? Sites::getCurrentSite()->handle : $siteHandle,
            'value' => $result['value'],
            'reason' => $result['reason'],
        ];
    }

    public static function findUser(string $who): ?User
    {
        $who = trim($who);

        if ($who === '' || mb_strlen($who) > 255) {
            return null;
        }

        $query = fn () => User::find()->status(null);

        return ctype_digit($who)
            ? $query()->id((int) $who)->one()
            : ($query()->username($who)->one() ?? $query()->email($who)->one());
    }

    public static function canEdit(): bool
    {
        return Gate::check(Plugin::PERMISSION_EDIT);
    }

    /** Definitions live in project config, so they follow `allowAdminChanges`. */
    public static function canDefine(): bool
    {
        return Cms::config()->allowAdminChanges;
    }

    /** @return array<string, string> */
    public static function modeLabels(): array
    {
        return [
            'inherit' => t('Same as the flag', category: 'bunting'),
            Flag::MODE_OFF => t('Off', category: 'bunting'),
            Flag::MODE_ON => t('On for everyone', category: 'bunting'),
            Flag::MODE_RULES => t('By rules (groups, percentage)', category: 'bunting'),
        ];
    }

    /** @return array<string, string> */
    public static function reasonLabels(): array
    {
        return [
            'override' => t('a per-user override', category: 'bunting'),
            'off' => t('the flag is off here', category: 'bunting'),
            'on' => t('the flag is on for everyone here', category: 'bunting'),
            'group' => t('the user is in a targeted group', category: 'bunting'),
            'rollout' => t('the user is inside the percentage rollout', category: 'bunting'),
            'no-match' => t('no rule matched', category: 'bunting'),
            'preview' => t('an admin preview', category: 'bunting'),
        ];
    }

    private static function rolloutSummary(string $mode, Flag $flag): string
    {
        return match ($mode) {
            Flag::MODE_ON => t('On', category: 'bunting'),
            Flag::MODE_RULES => t('Rules: {groups} groups, {percentage}%', ['groups' => count($flag->groupUids), 'percentage' => $flag->percentage], category: 'bunting'),
            default => t('Off', category: 'bunting'),
        };
    }

    private static function siteSummary(Flag $flag): string
    {
        $parts = [];

        foreach ($flag->siteModes as $uid => $mode) {
            $handle = rescue(fn () => Sites::getSiteByUid($uid, true)->handle, null, false);

            if ($handle !== null) {
                $parts[] = $handle.': '.self::rolloutSummary($mode, $flag);
            }
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    /** @return list<string> */
    private static function lines(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $value) ?: []), fn (string $l) => $l !== '')));
    }
}
