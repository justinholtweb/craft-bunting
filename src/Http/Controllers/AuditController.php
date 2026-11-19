<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Http\Controllers;

use CraftCms\Cms\Http\Responses\CpScreenResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use JustinHoltWeb\Bunting\Flags\AuditLog;
use JustinHoltWeb\Bunting\Flags\Flag;

use function CraftCms\Cms\t;

/** Bunting → Audit log: who changed which flag, how, and when. */
class AuditController
{
    public const int PER_PAGE = 50;

    public function __invoke(Request $request, AuditLog $audit): CpScreenResponse
    {
        $flag = trim((string) $request->query('flag', ''));
        $flag = preg_match(Flag::HANDLE_PATTERN, $flag) ? $flag : '';
        $total = $audit->count($flag ?: null);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        return new CpScreenResponse()
            ->title(t('Audit log', category: 'bunting'))
            ->contentTemplate('bunting/_audit', [
                'rows' => AuditLog::present($audit->recent($flag ?: null, self::PER_PAGE, ($page - 1) * self::PER_PAGE), showUsers: Gate::check('viewUsers')),
                'flag' => $flag,
                'page' => $page,
                'pages' => $pages,
            ]);
    }
}
