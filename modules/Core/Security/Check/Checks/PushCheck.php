<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check\Checks;

use Config\Push;
use Modules\Core\Push\WebPush;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;

/**
 * `secrets.pushKeys`: the VAPID keys of the Web Push channel (D-44-02). A warning when push is
 * switched on (`Core.pushEnabled`) without a valid key pair or contact; information otherwise.
 */
final class PushCheck implements SecurityCheck
{
    private const GROUP = 'secrets';

    public function __construct(private readonly ?bool $switchedOn = null, private readonly ?Push $config = null)
    {
    }

    public function groups(): array
    {
        return [self::GROUP];
    }

    public function run(SecurityReport $report): void
    {
        $on      = $this->switchedOn ?? WebPush::switchedOn();
        $problem = WebPush::problem($this->config ?? config(Push::class));
        $title   = lang('SecurityCheck.push.title');

        if ($problem === null) {
            $report->info(self::GROUP, 'secrets.pushKeys', $title, lang($on ? 'SecurityCheck.push.on' : 'SecurityCheck.push.offReady'));
        } elseif ($on) {
            $report->warning(self::GROUP, 'secrets.pushKeys', $title, lang('SecurityCheck.push.problem.' . $problem), lang('SecurityCheck.push.fix'));
        } else {
            $report->info(self::GROUP, 'secrets.pushKeys', $title, lang('SecurityCheck.push.off'), lang('SecurityCheck.push.fix'));
        }
    }
}
