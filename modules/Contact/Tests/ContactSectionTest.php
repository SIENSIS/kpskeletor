<?php

declare(strict_types=1);

namespace Modules\Contact\Tests;

use Modules\Contact\Sections\ContactFormSection;
use Modules\Contact\Services\FormToken;
use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionRegistry;

/**
 * The section `contact.form`: registered from the manifest, it shows only the recipients it
 * offers (none chosen = every active one) and signs them, with the page's path, in its token.
 *
 * @internal
 */
final class ContactSectionTest extends ContactTestCase
{
    public function testTheTypeIsRegistered(): void
    {
        $this->assertInstanceOf(ContactFormSection::class, (new SectionRegistry())->get('contact.form'));
    }

    public function testTheSectionOffersItsRecipientsAndSignsThem(): void
    {
        $office = $this->recipient(['ca' => 'Secretaria'], 'secretaria@example.com');
        $this->recipient(['ca' => 'Direcció']);
        $type    = new ContactFormSection();
        $context = new RenderContext('ca', 'ca');

        $html = $type->render(new SectionData('contact.form', ['recipients' => [(string) $office]], ['ca' => ['heading' => 'Escriu-nos', 'intro' => '']], [], false, 7), $context);
        $this->assertStringContainsString('Escriu-nos', $html);
        $this->assertStringContainsString('Secretaria', $html);
        $this->assertStringNotContainsString('Direcció', $html);
        $this->assertStringNotContainsString('secretaria@example.com', $html);
        $this->assertStringContainsString('id="contact-7-form"', $html);

        preg_match('/name="kp_token" value="([^"]+)"/', $html, $match);
        $token = FormToken::verify(html_entity_decode($match[1] ?? ''), time() + 10);
        $this->assertTrue($token['ok']);
        $this->assertSame([$office], $token['recipients']);

        $every = $type->render(new SectionData('contact.form', ['recipients' => []], [], [], false, 8), $context);
        $this->assertStringContainsString('Secretaria', $every);
        $this->assertStringContainsString('Direcció', $every);
    }
}
