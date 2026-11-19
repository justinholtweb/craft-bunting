<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use JustinHoltWeb\Bunting\Flags\Flags;
use Throwable;

use function CraftCms\Cms\currentUser;

/**
 * Admin previews: `?bunting-preview=new-nav:on,old-footer:off` turns flags on or
 * off for *your own* session only (`?bunting-preview=clear` stops). Needs the
 * “Preview flags” permission, checked on every request. Never stored in Pennant.
 */
final class Preview
{
    public const string PARAM = 'bunting-preview';

    public const string SESSION_KEY = 'bunting.preview';

    public const string PERMISSION = 'bunting:preview';

    public function __construct(private readonly Flags $flags) {}

    /** The previewed value of a flag for this request, or null. */
    public function value(string $handle): ?bool
    {
        $values = $this->values();

        return $values[$handle] ?? null;
    }

    /** @return array<string, bool> */
    public function values(): array
    {
        try {
            $request = request();

            if (! $request->hasSession() || ! $this->allowed()) {
                return [];
            }

            $values = $request->session()->get(self::SESSION_KEY, []);
        } catch (Throwable) {
            return [];
        }

        return is_array($values) ? array_filter($values, is_bool(...)) : [];
    }

    /** Applies `?bunting-preview=` to the session. Returns false if the request may not preview. */
    public function capture(Request $request): bool
    {
        $param = $request->query(self::PARAM);

        if (! is_string($param) || ! $request->hasSession() || ! $this->allowed()) {
            return false;
        }

        if (trim($param) === 'clear') {
            $request->session()->forget(self::SESSION_KEY);

            return true;
        }

        $values = $this->values();

        foreach (explode(',', $param) as $pair) {
            [$handle, $state] = array_pad(explode(':', trim($pair), 2), 2, 'on');

            // Only real flags, so the session can't be stuffed with arbitrary keys.
            if ($this->flags->get($handle) === null) {
                continue;
            }

            match ($state) {
                'on', '1', 'true' => $values[$handle] = true,
                'off', '0', 'false' => $values[$handle] = false,
                default => null,
            };
        }

        $request->session()->put(self::SESSION_KEY, $values);

        return true;
    }

    private function allowed(): bool
    {
        return currentUser() !== null && Gate::check(self::PERMISSION);
    }
}
