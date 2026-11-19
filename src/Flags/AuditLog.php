<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Flags;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\currentUserElement;

/**
 * Who changed what: definitions (from the CP or a project config apply),
 * rollouts, overrides and purges. Rows outlive the flag and the person.
 */
final class AuditLog
{
    public const string TABLE = 'bunting_audit';

    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string DELETED = 'deleted';

    public const string ROLLOUT = 'rollout';

    public const string OVERRIDE = 'override';

    public const string PURGED = 'purged';

    /** @param  array<string, mixed>|null  $changes */
    public function record(string $action, string $flagHandle, ?string $flagUid, ?array $changes = null): void
    {
        $user = currentUserElement();

        DB::table(self::TABLE)->insert([
            'flagUid' => $flagUid,
            'flagHandle' => $flagHandle,
            'action' => $action,
            'userId' => $user?->id,
            // A snapshot, so the log still reads right after the user is renamed or deleted.
            'userName' => $user?->username,
            'changes' => $changes === null ? null : json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'dateCreated' => now()->utc(),
        ]);
    }

    /**
     * Before → after for the keys that changed.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $diff = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $from = $before[$key] ?? null;
            $to = $after[$key] ?? null;

            if (self::normalise($from) !== self::normalise($to)) {
                $diff[$key] = ['from' => $from, 'to' => $to];
            }
        }

        return $diff;
    }

    /**
     * @return list<array{id: int, flagHandle: string, flagUid: string|null, action: string, userId: int|null, userName: string|null, changes: array<string, mixed>|null, dateCreated: string}>
     */
    public function recent(?string $flagHandle = null, int $limit = 100, int $offset = 0): array
    {
        $query = DB::table(self::TABLE)->orderByDesc('dateCreated')->orderByDesc('id')->limit($limit)->offset($offset);

        $this->filter($query, $flagHandle);

        return $query->get()->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'flagHandle' => (string) $row->flagHandle,
            'flagUid' => $row->flagUid === null ? null : (string) $row->flagUid,
            'action' => (string) $row->action,
            'userId' => $row->userId === null ? null : (int) $row->userId,
            'userName' => $row->userName === null ? null : (string) $row->userName,
            'changes' => is_string($row->changes) ? json_decode($row->changes, true) : null,
            'dateCreated' => (string) $row->dateCreated,
        ])->values()->all();
    }

    public function count(?string $flagHandle = null): int
    {
        $query = DB::table(self::TABLE);
        $this->filter($query, $flagHandle);

        return $query->count();
    }

    /** A flag's rows, including those from before it was renamed (matched by UID). */
    private function filter(Builder $query, ?string $flagHandle): void
    {
        if ($flagHandle === null) {
            return;
        }

        $uid = DB::table(Flags::TABLE)->where('handle', $flagHandle)->value('uid');
        $query->where(fn ($q) => $q->where('flagHandle', $flagHandle)->when(is_string($uid), fn ($q) => $q->orWhere('flagUid', $uid)));
    }

    /**
     * Rows with their changes as readable lines, for the CP.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function present(array $rows): array
    {
        $show = fn (mixed $v): string => match (true) {
            $v === null => '—',
            $v === true => 'on',
            $v === false => 'off',
            is_array($v) => $v === [] ? '—' : (json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            default => (string) (is_scalar($v) ? $v : ''),
        };

        return array_map(function (array $row) use ($show): array {
            $lines = [];
            $changes = is_array($row['changes'] ?? null) ? $row['changes'] : [];

            if ($row['action'] === self::OVERRIDE) {
                $lines[] = ($changes['user']['label'] ?? '?').': '.$show($changes['override']['from'] ?? null).' → '.$show($changes['override']['to'] ?? null);
            } else {
                foreach ($changes as $key => $change) {
                    $lines[] = $key.': '.$show($change['from'] ?? null).' → '.$show($change['to'] ?? null);
                }
            }

            return [...$row, 'lines' => $lines];
        }, $rows);
    }

    private static function normalise(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(self::normalise(...), $value);
        }

        return $value;
    }
}
