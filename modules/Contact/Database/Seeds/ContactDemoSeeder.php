<?php

declare(strict_types=1);

namespace Modules\Contact\Database\Seeds;

use CodeIgniter\I18n\Time;
use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;

/**
 * Demo data of the Contact module (`php spark modules:demo Contact`): three recipients with
 * names in Catalan, Spanish and English ("Secretaria" notifies the `editor` group and has an
 * address, "Direcció" the `admin` group, "Informació general" only an address) and three
 * messages, one per status. Every row goes through `$demo`.
 */
final class ContactDemoSeeder extends DemoSeeder
{
    public function load(DemoData $demo): void
    {
        $now = Time::now();

        $office = $this->recipient($demo, 1, 'secretaria@example.com', 'editor', ['ca' => 'Secretaria', 'es' => 'Secretaría', 'en' => 'Office']);
        $head   = $this->recipient($demo, 2, null, 'admin', ['ca' => 'Direcció', 'es' => 'Dirección', 'en' => 'Management']);
        $this->recipient($demo, 3, 'info@example.com', null, ['ca' => 'Informació general', 'es' => 'Información general', 'en' => 'General information']);

        $messages = [
            [$office, 'Secretaria', 'Marta Riera', 'marta@example.com', 'Horari de matrícula', "Bon dia,\nvoldria saber l'horari de matrícula del mes vinent.\nGràcies.", 'ca', 'new', 1],
            [$head, 'Direcció', 'Joan Puig', 'joan@example.com', 'Proposta de col·laboració', "Hola,\nsom una entitat del barri i voldríem proposar-vos una activitat conjunta.", 'ca', 'answered', 4],
            [$office, 'Secretaria', 'Laura Gómez', 'laura@example.com', 'Certificado', 'Necesito un certificado de asistencia del curso pasado.', 'es', 'archived', 20],
        ];

        foreach ($messages as [$recipient, $name, $from, $email, $subject, $text, $locale, $status, $days]) {
            $date = $now->subDays($days)->toDateTimeString();
            $demo->insert('contact_messages', [
                'recipient_id'   => $recipient,
                'recipient_name' => $name,
                'name'           => $from,
                'email'          => $email,
                'subject'        => $subject,
                'message'        => $text,
                'locale'         => $locale,
                'status'         => $status,
                'answered_at'    => $status === 'answered' ? $date : null,
                'created_at'     => $date,
                'updated_at'     => $date,
            ]);
        }
    }

    /**
     * @param array<string, string> $names locale => name
     */
    private function recipient(DemoData $demo, int $position, ?string $emails, ?string $group, array $names): int
    {
        $now = Time::now()->toDateTimeString();
        $id  = (int) $demo->insert('contact_recipients', [
            'emails'     => $emails,
            'group_name' => $group,
            'position'   => $position,
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($names as $locale => $name) {
            $demo->insert('contact_recipient_translations', ['recipient_id' => $id, 'locale' => $locale, 'name' => $name]);
        }

        return $id;
    }
}
