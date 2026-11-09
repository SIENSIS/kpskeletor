<?php

declare(strict_types=1);

namespace Modules\News\Tests;

/**
 * Visibility (public / private / groups, drafts, scheduled, expired) on the reader screen, its
 * details page and "mark as read" (D-00-42).
 *
 * @internal
 */
final class ReaderTest extends NewsTestCase
{
    public function testTheReaderListsOnlyWhatTheUserMayRead(): void
    {
        $this->newsPost('Pública', ['visibility' => 'public']);
        $this->newsPost('Privada');
        $this->newsPost('Per a usuaris', ['visibility' => 'groups'], ['user']);
        $this->newsPost('Per a editors', ['visibility' => 'groups'], ['editor']);
        $this->newsPost('Esborrany', ['status' => 'draft']);
        $this->newsPost('Programada', ['status' => 'scheduled', 'published_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $this->newsPost('Caducada', ['type' => 'notice', 'expires_at' => date('Y-m-d H:i:s', time() - 60)]);
        $this->newsPost('Esborrada', ['deleted_at' => date('Y-m-d H:i:s')]);
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $page = $this->body('GET', 'backend/news');

        foreach (['Pública', 'Privada', 'Per a usuaris'] as $title) {
            $this->assertStringContainsString($title, $page);
        }

        foreach (['Per a editors', 'Esborrany', 'Programada', 'Caducada', 'Esborrada'] as $title) {
            $this->assertStringNotContainsString($title, $page);
        }
    }

    public function testADetailThatCannotBeReadIsNotFound(): void
    {
        $visible = $this->newsPost('Visible');
        $hidden  = $this->newsPost('Amagada', ['visibility' => 'groups'], ['editor']);
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->assertStringContainsString('Text de Visible', $this->body('GET', 'backend/news/read/' . $this->code($visible)));
        $this->assertNotFound('GET', 'backend/news/read/' . $this->code($hidden));
        $this->assertNotFound('GET', 'backend/news/read/' . $hidden);
        $this->assertNotFound('POST', 'backend/news/read/' . $this->code($hidden) . '/mark');
        $this->assertSame(0, db_connect()->table('news_reads')->countAllResults());
    }

    public function testMarkAsRead(): void
    {
        $id  = $this->newsPost('Per llegir');
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->assertStringContainsString('Mark as read', $this->body('GET', 'backend/news/read/' . $this->code($id)));
        $this->send('POST', 'backend/news/read/' . $this->code($id) . '/mark')->assertRedirectTo(site_url('backend/news/read/' . $this->code($id)));
        $this->send('POST', 'backend/news/read/' . $this->code($id) . '/mark', ['return' => 'https://evil.example.com/'])->assertRedirectTo(site_url('backend/news/read/' . $this->code($id)));

        $this->assertSame(1, db_connect()->table('news_reads')->where('news_id', $id)->where('user_id', $ana->id)->countAllResults(), 'Marked once.');
        $this->assertStringNotContainsString('Mark as read', $this->body('GET', 'backend/news/read/' . $this->code($id)));
        $this->assertStringNotContainsString('Per llegir', $this->body('GET', 'backend/news?unread=1'));
    }

    public function testTheReaderNeedsItsPermissionAndALogin(): void
    {
        $this->send('GET', 'backend/news')->assertRedirectTo('/login');

        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $this->send('GET', 'backend/news')->assertOK();
        $this->assertTrue($ana->can('news-read.view'), 'Granted to every group.');
    }

    public function testFiltersByType(): void
    {
        $this->newsPost('Un article');
        $this->newsPost('Un avís', ['type' => 'notice']);
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $page = $this->body('GET', 'backend/news?type=notice');
        $this->assertStringContainsString('Un avís', $page);
        $this->assertStringNotContainsString('Un article', $page);
    }
}
