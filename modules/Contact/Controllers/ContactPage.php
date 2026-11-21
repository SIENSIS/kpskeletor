<?php

declare(strict_types=1);

namespace Modules\Contact\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Auth\Services\Captcha;
use Modules\Contact\Services\ContactForm;
use Modules\Contact\Services\ContactMessages;
use Modules\Contact\Services\ContentRules;
use Modules\Contact\Services\FormToken;
use Modules\Contact\Services\SenderHistory;
use Modules\Core\Controllers\FrontendController;

/**
 * The public contact form (D-48-01, D-48-02):
 *
 * - `GET contact`: the page with the form (every active recipient);
 * - `POST contact` (CSRF, `ratelimit:forms`): the form of this page or of a `contact.form`
 *   section. The layers, in order: the signed token (time trap, the recipients offered and the
 *   path to go back to), the honeypot, the captcha (always, or when suspicious), validation, the
 *   recipient among those offered, the content rules and the rate limits per IP and email. A
 *   refusal by a hidden layer is a `security_log()` entry with its reason and shows a generic
 *   message; a valid message is stored, its recipient is told, and the visitor goes back
 *   (post/redirect/get) with a confirmation. Nothing is ever sent to the visitor.
 * - `GET contact/captcha`: the captcha image (Auth's local captcha, D-00-53).
 */
final class ContactPage extends FrontendController
{
    /** The honeypot input: hidden from people, filled by robots. */
    public const HONEYPOT = 'website';

    /** @var array<string, string> */
    public const RULES = [
        'name'    => 'required|max_length[120]',
        'email'   => 'required|valid_email|max_length[190]',
        'subject' => 'required|min_length[3]|max_length[190]',
        'message' => 'required|min_length[10]|max_length[5000]',
        'privacy' => 'required|in_list[1]',
    ];

    public function show(): string
    {
        return $this->page((new ContactForm())->data([], '/contact'));
    }

    public function send(): RedirectResponse|ResponseInterface|string
    {
        $form    = new ContactForm();
        $history = new SenderHistory();
        $ip      = $this->request->getIPAddress();
        $data    = [];

        foreach (['name', 'email', 'subject', 'message'] as $field) {
            $value        = $this->request->getPost($field);
            $data[$field] = is_string($value) ? trim($value) : '';
        }

        $recipient = $this->request->getPost('recipient');
        $recipient = is_string($recipient) && ctype_digit($recipient) ? (int) $recipient : 0;
        $old       = $data + ['recipient' => (string) $recipient, 'privacy' => $this->request->getPost('privacy') === '1' ? '1' : ''];
        $token     = FormToken::verify($this->request->getPost('kp_token'));
        $only      = $token['recipients'];
        $return    = $token['ok'] ? $token['return'] : '/contact';

        // 1–2. The signed token (time trap) and the honeypot: robots get the generic message.
        if (! $token['ok']) {
            return $this->refuse($token['reason'], $form, $old, $data['email']);
        }

        $honeypot = $this->request->getPost(self::HONEYPOT);

        if ($honeypot !== null && $honeypot !== '') {
            return $this->refuse('honeypot', $form, $old, $data['email'], $only, $return);
        }

        // 3. The captcha (single use, so it is checked once whatever else fails).
        if ($form->captchaRequired($data['email'])) {
            if (! (new Captcha())->verify($this->request->getPost('captcha'))) {
                $this->logRefusal('captcha', $data['email']);
                session()->set('contact_suspicious', true);

                return $this->page($form->data($only, $return, 'contact', $old, ['captcha' => lang('Contact.form.captchaWrong')], null, true));
            }
        }

        // 4. Validation and the recipient (among those the form offered, and active).
        if (! $this->validateData($old, self::rules())) {
            return $this->page($form->data($only, $return, 'contact', $old, $this->validator?->getErrors() ?? []));
        }

        $offered = array_column((new \Modules\Contact\Services\Recipients())->active(null, $only), 'id');

        if (! in_array($recipient, $offered, true)) {
            return $this->refuse('recipient', $form, $old, $data['email'], $only, $return);
        }

        // 5. Content rules (header injection, links, blocked words, script).
        $reason = (new ContentRules())->check($data);

        if ($reason !== null) {
            return $this->refuse($reason, $form, $old, $data['email'], $only, $return);
        }

        // 6. Rate limits per IP and per email.
        $wait = $history->allow($ip, $data['email']);

        if ($wait > 0) {
            $this->response->setStatusCode(429)->setHeader('Retry-After', (string) $wait);

            return $this->refuse('rate_limit', $form, $old, $data['email'], $only, $return);
        }

        /** @var array{name: string, email: string, subject: string, message: string} $data */
        (new ContactMessages())->submit($data, $recipient, $this->request->getLocale());
        $history->record($ip, $data['email']);

        return safe_redirect($return, '/contact')->with('success', lang('Contact.form.sent'));
    }

    /**
     * The validation rules with their labels and messages in the visitor's language.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function rules(): array
    {
        $rules = [];

        foreach (self::RULES + ['recipient' => 'required|is_natural_no_zero'] as $field => $rule) {
            $rules[$field] = ['label' => lang('Contact.fields.' . $field), 'rules' => $rule];
        }

        $rules['privacy']['errors']   = ['required' => lang('Contact.form.privacyRequired'), 'in_list' => lang('Contact.form.privacyRequired')];
        $rules['recipient']['errors'] = ['required' => lang('Contact.form.recipientRequired'), 'is_natural_no_zero' => lang('Contact.form.recipientRequired')];

        return $rules;
    }

    public function captcha(): ResponseInterface
    {
        return $this->response
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setBody((new Captcha())->image());
    }

    /**
     * A refusal by a hidden layer: logged with its reason, the form again with a generic message.
     *
     * @param array<string, string> $old
     * @param list<int>             $only
     */
    private function refuse(string $reason, ContactForm $form, array $old, string $email, array $only = [], string $return = '/contact'): string
    {
        $this->logRefusal($reason, $email);
        session()->set('contact_suspicious', true);
        (new SenderHistory())->record($this->request->getIPAddress());

        return $this->page($form->data($only, $return, 'contact', $old, [], lang('Contact.form.refused')));
    }

    private function logRefusal(string $reason, string $email): void
    {
        security_log('Contact', 'message_refused', [
            'resource' => 'contact-messages',
            'summary'  => lang('Contact.reasons.' . $reason),
            'changes'  => ['reason' => $reason, 'email_hash' => $email === '' ? null : substr(hash('sha256', mb_strtolower($email)), 0, 16)],
        ]);
    }

    /**
     * @param array<string, mixed> $form
     */
    private function page(array $form): string
    {
        $this->meta([
            'description' => lang('Contact.page.intro'),
            'canonical'   => site_url('contact'),
        ]);

        return $this->render('Modules\Contact\Views\public\contact', [
            'title'       => lang('Contact.page.title'),
            'form'        => $form,
            'breadcrumbs' => [['label' => lang('Contact.page.title')]],
        ]);
    }
}
