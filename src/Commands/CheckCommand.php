<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JustinHoltWeb\Bunting\Bunting;

/** Whether a flag is on for a user (or a guest) on a site, and why. */
class CheckCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:bunting:check {flag} {--user= : Username, email or ID (default: a guest)} {--site= : Site handle (default: the primary site)}';

    protected $description = 'Shows whether a flag is on for a user or guest on a site, and why';

    public function handle(Bunting $bunting): int
    {
        $user = null;

        if (($u = $this->option('user')) !== null) {
            $u = (string) $u;
            $user = ctype_digit($u)
                ? User::find()->id((int) $u)->status(null)->one()
                : User::find()->username($u)->status(null)->one() ?? User::find()->email($u)->status(null)->one();

            if (! $user instanceof User) {
                $this->error('Unknown user.');

                return self::FAILURE;
            }
        }

        try {
            $scope = $bunting->scope($user, $this->option('site') === null ? null : (string) $this->option('site'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $explained = $bunting->explain((string) $this->argument('flag'), $scope);

        if ($explained === null) {
            $this->error('Unknown flag.');

            return self::FAILURE;
        }

        $this->line(($explained['value'] ? 'on' : 'off').' ('.$explained['reason'].')');

        return self::SUCCESS;
    }
}
