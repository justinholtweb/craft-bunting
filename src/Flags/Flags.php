<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Flags;

use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Str;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\FeatureManager;
use Throwable;

/**
 * Flag storage. A flag's definition (handle, name, description, public, gated URIs)
 * lives in project config under `bunting.flags.<uid>` and is mirrored into
 * `bunting_flags`; its rollout (mode, site modes, groups, percentage) and per-user
 * overrides live only in the database, so flags can be flipped on production
 * even with `allowAdminChanges` off.
 */
final class Flags
{
    public const string CONFIG_PATH = 'bunting.flags';

    public const string TABLE = 'bunting_flags';

    public const string OVERRIDES_TABLE = 'bunting_overrides';

    /** @var array<string, Flag>|null Handle → flag, for this request. */
    private ?array $flags = null;

    /** @var array<int, array<int, bool>> Flag ID → user ID → override, for this request. */
    private array $overrides = [];

    public function __construct(private readonly AuditLog $audit) {}

    /** @return array<string, Flag> Handle → flag, by name. */
    public function all(): array
    {
        if ($this->flags === null) {
            $this->flags = [];

            foreach (DB::table(self::TABLE)->orderBy('name')->get() as $row) {
                $flag = self::fromRow($row);
                $this->flags[$flag->handle] = $flag;
            }
        }

        return $this->flags;
    }

    public function get(string $handle): ?Flag
    {
        return $this->all()[$handle] ?? null;
    }

    public function getByUid(string $uid): ?Flag
    {
        foreach ($this->all() as $flag) {
            if ($flag->uid === $uid) {
                return $flag;
            }
        }

        return null;
    }

    public function forget(): void
    {
        $this->flags = null;
        $this->overrides = [];
    }

    /** Saves the definition to project config; the change handler writes the database. */
    public function saveDefinition(Flag $flag): void
    {
        $flag->uid = $flag->uid !== '' ? $flag->uid : Str::uuid()->toString();
        ProjectConfig::set(self::CONFIG_PATH.'.'.$flag->uid, $flag->definitionConfig(), "Save Bunting flag “{$flag->handle}”");
        $this->forget();
    }

    public function deleteDefinition(Flag $flag): void
    {
        ProjectConfig::remove(self::CONFIG_PATH.'.'.$flag->uid, "Delete Bunting flag “{$flag->handle}”");
        $this->forget();
    }

    /**
     * Saves the rollout, audits it and drops Pennant's stored values for the flag.
     *
     * @param  array{mode?: string, siteModes?: array<string, string>, groupUids?: list<string>, percentage?: int}  $rollout
     */
    public function saveRollout(Flag $flag, array $rollout): bool
    {
        $before = $flag->rollout();
        $after = array_replace($before, $rollout);
        $diff = AuditLog::diff($before, $after);

        if ($diff === []) {
            return false;
        }

        DB::table(self::TABLE)->where('uid', $flag->uid)->update([
            'mode' => $after['mode'],
            'siteModes' => json_encode((object) $after['siteModes'], JSON_THROW_ON_ERROR),
            'groupUids' => json_encode(array_values(array_filter($after['groupUids'], is_string(...))), JSON_THROW_ON_ERROR),
            'percentage' => $after['percentage'],
            'dateUpdated' => now()->utc(),
        ]);

        $this->audit->record(AuditLog::ROLLOUT, $flag->handle, $flag->uid, $diff);
        $this->purgeStored($flag->handle);
        $this->forget();

        return true;
    }

    /**
     * Per-user overrides for a flag.
     *
     * @return array<int, bool> User ID → value
     */
    public function overrides(Flag $flag): array
    {
        if ($flag->id === null) {
            return [];
        }

        return $this->overrides[$flag->id] ??= DB::table(self::OVERRIDES_TABLE)
            ->where('flagId', $flag->id)
            ->pluck('value', 'userId')
            ->map(fn (mixed $value): bool => (bool) $value)
            ->all();
    }

    public function override(Flag $flag, int $userId): ?bool
    {
        return $this->overrides($flag)[$userId] ?? null;
    }

    /**
     * Sets (true/false) or clears (null) a user's override, audits it and drops
     * that user's stored values for the flag.
     */
    public function setOverride(Flag $flag, int $userId, string $userUid, ?bool $value, string $userLabel): bool
    {
        $current = $this->override($flag, $userId);

        if ($current === $value || $flag->id === null) {
            return false;
        }

        if ($value === null) {
            DB::table(self::OVERRIDES_TABLE)->where(['flagId' => $flag->id, 'userId' => $userId])->delete();
        } else {
            $now = now()->utc();
            DB::table(self::OVERRIDES_TABLE)->upsert(
                [['flagId' => $flag->id, 'userId' => $userId, 'value' => $value, 'dateCreated' => $now, 'dateUpdated' => $now]],
                ['flagId', 'userId'],
                ['value', 'dateUpdated'],
            );
        }

        $this->audit->record(AuditLog::OVERRIDE, $flag->handle, $flag->uid, [
            'user' => ['id' => $userId, 'label' => $userLabel],
            'override' => ['from' => $current, 'to' => $value],
        ]);
        $this->purgeStoredForUser($userUid, [$flag->handle]);
        unset($this->overrides[$flag->id]);

        return true;
    }

    /** Drops Pennant's stored values for the given flags (all flags when null). */
    public function purgeStored(?string ...$handles): void
    {
        $handles = array_values(array_filter($handles, fn (?string $h) => $h !== null));
        app(FeatureManager::class)->store('bunting')->purge($handles === [] ? null : $handles);
    }

    /**
     * Drops one user's stored values (e.g. after their groups changed).
     *
     * @param  list<string>|null  $handles
     */
    public function purgeStoredForUser(string $userUid, ?array $handles = null): void
    {
        $query = DB::table('bunting_features')->where('scope', 'like', '%|user:'.$userUid);

        if ($handles !== null) {
            $query->whereIn('name', $handles);
        }

        $query->delete();
        app(FeatureManager::class)->store('bunting')->flushCache();
    }

    /** Drops every stored value for a site (e.g. after it was deleted). */
    public function purgeStoredForSite(string $siteUid): void
    {
        DB::table('bunting_features')->where('scope', 'like', 'site:'.$siteUid.'|%')->delete();
        app(FeatureManager::class)->store('bunting')->flushCache();
    }

    /** Records that a flag was checked, at most once an hour per flag (for the stale-flag report). */
    public function touch(Flag $flag): void
    {
        try {
            if (Cache::add('bunting:checked:'.$flag->uid, 1, 3600)) {
                DB::table(self::TABLE)->where('uid', $flag->uid)->update(['lastCheckedAt' => now()->utc()]);
            }
        } catch (Throwable) {
            // Never let bookkeeping break a page.
        }
    }

    /** @return list<Flag> Flags not checked in `$days` days (or never). */
    public function stale(int $days): array
    {
        $cutoff = now()->utc()->subDays($days);

        return array_values(array_filter(
            $this->all(),
            fn (Flag $flag) => $flag->lastCheckedAt === null || $flag->lastCheckedAt < $cutoff->toDateTimeImmutable(),
        ));
    }

    // Project config ------------------------------------------------------------------------

    public function handleChangedFlag(ConfigEvent $event): void
    {
        $uid = (string) ($event->tokenMatches[0] ?? '');
        $config = is_array($event->newValue) ? $event->newValue : [];

        if ($uid === '' || ! isset($config['handle'])) {
            return;
        }

        $existing = DB::table(self::TABLE)->where('uid', $uid)->first();
        $definition = self::normaliseDefinition($config);
        $now = now()->utc();

        if ($existing === null) {
            DB::table(self::TABLE)->insert([
                ...self::definitionColumns($definition),
                'mode' => Flag::MODE_OFF,
                'percentage' => 0,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => $uid,
            ]);
            $this->audit->record(AuditLog::CREATED, $definition['handle'], $uid, AuditLog::diff([], $definition));
        } else {
            $before = self::normaliseDefinition([
                'handle' => $existing->handle,
                'name' => $existing->name,
                'description' => $existing->description,
                'public' => (bool) $existing->public,
                'uriPatterns' => is_string($existing->uriPatterns) ? json_decode($existing->uriPatterns, true) : [],
            ]);
            DB::table(self::TABLE)->where('uid', $uid)->update([...self::definitionColumns($definition), 'dateUpdated' => $now]);

            if (($diff = AuditLog::diff($before, $definition)) !== []) {
                $this->audit->record(AuditLog::UPDATED, $definition['handle'], $uid, $diff);
            }

            // Values were stored under the old handle.
            if ($before['handle'] !== $definition['handle']) {
                $this->purgeStored($before['handle']);
            }
        }

        $this->forget();
    }

    public function handleDeletedFlag(ConfigEvent $event): void
    {
        $uid = (string) ($event->tokenMatches[0] ?? '');
        $row = DB::table(self::TABLE)->where('uid', $uid)->first();

        if ($row === null) {
            return;
        }

        DB::table(self::TABLE)->where('uid', $uid)->delete();
        $this->audit->record(AuditLog::DELETED, (string) $row->handle, $uid);
        $this->purgeStored((string) $row->handle);
        $this->forget();
    }

    /** Mirrors project config into the table (after install, or to repair drift). */
    public function syncFromProjectConfig(): void
    {
        $configured = ProjectConfig::get(self::CONFIG_PATH) ?? [];
        $configured = is_array($configured) ? $configured : [];

        foreach ($configured as $uid => $config) {
            $this->handleChangedFlag(new class(self::CONFIG_PATH.'.'.$uid, null, $config, [(string) $uid]) extends ConfigEvent {});
        }

        DB::table(self::TABLE)->whereNotIn('uid', array_map('strval', array_keys($configured)))->delete();
        $this->forget();
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return array{handle: string, name: string, description: string|null, public: bool, uriPatterns: list<string>}
     */
    public static function normaliseDefinition(array $config): array
    {
        $description = trim((string) ($config['description'] ?? ''));
        $patterns = is_array($config['uriPatterns'] ?? null) ? $config['uriPatterns'] : [];

        return [
            'handle' => (string) $config['handle'],
            'name' => (string) ($config['name'] ?? $config['handle']),
            'description' => $description === '' ? null : $description,
            'public' => (bool) ($config['public'] ?? false),
            'uriPatterns' => array_values(array_filter(array_map(fn (mixed $p) => trim((string) $p), $patterns), fn (string $p) => $p !== '')),
        ];
    }

    /**
     * @param  array{handle: string, name: string, description: string|null, public: bool, uriPatterns: list<string>}  $definition
     * @return array<string, mixed>
     */
    private static function definitionColumns(array $definition): array
    {
        return [
            ...$definition,
            'uriPatterns' => json_encode($definition['uriPatterns'], JSON_THROW_ON_ERROR),
        ];
    }

    private static function fromRow(object $row): Flag
    {
        $json = fn (mixed $value): array => is_string($value) && is_array($decoded = json_decode($value, true)) ? $decoded : [];
        $date = fn (mixed $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));

        return new Flag(
            id: (int) $row->id,
            uid: (string) $row->uid,
            handle: (string) $row->handle,
            name: (string) $row->name,
            description: $row->description === null ? null : (string) $row->description,
            public: (bool) $row->public,
            uriPatterns: array_values(array_map('strval', $json($row->uriPatterns))),
            mode: in_array($row->mode, Flag::MODES, true) ? (string) $row->mode : Flag::MODE_OFF,
            siteModes: array_map('strval', $json($row->siteModes)),
            groupUids: array_values(array_map('strval', $json($row->groupUids))),
            percentage: max(0, min(100, (int) $row->percentage)),
            lastCheckedAt: $date($row->lastCheckedAt),
            dateUpdated: $date($row->dateUpdated),
        );
    }
}
