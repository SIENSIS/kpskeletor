<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use Config\Push;
use Modules\Core\Install\EnvWriter;
use Throwable;

/**
 * Writes the VAPID keys of the `push` channel into `.env` (`php spark push:keys`, D-44-02) and
 * applies them to the running process. Rotating keys removes every subscription: browsers
 * subscribed with the old public key cannot receive messages signed with the new one, and
 * users turn push on again on each device.
 */
final class PushKeyWriter
{
    public function __construct(
        private readonly string $envPath = ROOTPATH . '.env',
        private readonly ?PushSubscriptions $subscriptions = null,
    ) {
    }

    public function envWritable(): bool
    {
        return is_file($this->envPath) && is_writable($this->envPath);
    }

    /**
     * @param string $subject contact written when `.env` has none (`push.subject`)
     *
     * @return int devices removed
     */
    public function write(string $subject): int
    {
        $values  = WebPush::newKeys();
        $content = (string) file_get_contents($this->envPath);

        foreach ($values as $key => $value) {
            $content = EnvWriter::withValue($content, $key, $value);
        }

        $config = config(Push::class);

        if (! WebPush::validSubject($config->subject)) {
            $content                = EnvWriter::withValue($content, 'push.subject', $subject);
            $values['push.subject'] = $subject;
        }

        (new EnvWriter())->write($this->envPath, $content);

        foreach ($values as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            service('superglobals')->setServer($key, $value);
        }

        $config->vapidPublicKey  = $values['push.vapidPublicKey'];
        $config->vapidPrivateKey = $values['push.vapidPrivateKey'];
        $config->subject         = $values['push.subject'] ?? $config->subject;
        WebPush::reset();

        try {
            return ($this->subscriptions ?? new PushSubscriptions())->removeAll();
        } catch (Throwable) {
            return 0; // tables not created yet
        }
    }
}
