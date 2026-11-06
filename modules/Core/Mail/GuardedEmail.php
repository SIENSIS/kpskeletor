<?php

declare(strict_types=1);

namespace Modules\Core\Mail;

use CodeIgniter\Email\Email;
use Modules\Core\Http\OutboundAllowList;

/**
 * CodeIgniter's Email (the `email` service, app/Config/Services.php) with the outbound rule for
 * SMTP (decision D-00-29): SMTP is not HTTP, so instead of the OutboundClient the SMTP host must
 * be in the same allow-list (`Config\Outbound`, modules, `Core.outboundHosts`). A message to a
 * host outside it is not sent (`send()` returns false) and the refusal is logged.
 * `mail` and `sendmail` deliver locally and are not checked.
 */
class GuardedEmail extends Email
{
    private ?OutboundAllowList $allowList = null;

    public function setAllowList(OutboundAllowList $allowList): static
    {
        $this->allowList = $allowList;

        return $this;
    }

    /**
     * Whether the configured transport may be used.
     */
    public function transportAllowed(): bool
    {
        if (strtolower($this->protocol) !== 'smtp') {
            return true;
        }

        return ($this->allowList ?? new OutboundAllowList())->allows((string) $this->SMTPHost);
    }

    /**
     * @param bool $autoClear
     */
    public function send($autoClear = true)
    {
        if (! $this->transportAllowed()) {
            log_message('error', 'Email not sent: the SMTP host {host} is not in the outbound allow-list (Config\Outbound).', ['host' => (string) $this->SMTPHost]);
            security_log('Core', 'outbound_refused', ['resource' => 'outbound', 'summary' => 'SMTP ' . $this->SMTPHost]);

            return false;
        }

        return parent::send($autoClear);
    }
}
