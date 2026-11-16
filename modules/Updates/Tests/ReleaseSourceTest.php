<?php

declare(strict_types=1);

namespace Modules\Updates\Tests;

use Modules\Core\Http\OutboundResponse;
use Modules\Updates\Services\ReleaseSource;
use Modules\Updates\Services\UpdateException;

/**
 * Reading releases from GitHub and GitLab through the OutboundClient: repository syntax, the
 * newest semantic version (drafts, pre-releases and other tags ignored), private repositories
 * (token, API downloads) and errors.
 *
 * @internal
 */
final class ReleaseSourceTest extends UpdatesTestCase
{
    public function testRepositorySyntax(): void
    {
        $this->assertSame(['type' => 'github', 'host' => 'api.github.com', 'path' => 'acme/app'], ReleaseSource::parse('github:acme/app'));
        $this->assertSame(['type' => 'github', 'host' => 'api.github.com', 'path' => 'acme/app'], ReleaseSource::parse('https://github.com/acme/app.git'));
        $this->assertSame(['type' => 'gitlab', 'host' => 'gitlab.com', 'path' => 'group/sub/app'], ReleaseSource::parse('gitlab:group/sub/app'));
        $this->assertSame(['type' => 'gitlab', 'host' => 'git.example.org', 'path' => 'group/app'], ReleaseSource::parse('https://git.example.org/group/app'));

        foreach (['', 'acme/app', 'github:acme', 'github:acme/app/x', 'github:../x', 'http://git.example.org/a/b', 'https://user@git.example.org/a/b', 'github:acme/app?x=1'] as $bad) {
            $this->assertNull(ReleaseSource::parse($bad), $bad);
        }

        $this->expectException(UpdateException::class);
        new ReleaseSource('ftp://example.org/a/b');
    }

    public function testTheNewestReleaseIsChosen(): void
    {
        $this->publish([
            self::release('v0.3.0', 'draft', draft: true),
            self::release('v0.2.0-beta.1', prerelease: true),
            self::release('nightly'),
            self::release('v0.1.5'),
            self::release('v0.2.0', "## Changes\n- one"),
            self::release('v0.1.9'),
        ]);

        $latest = $this->source()->latest();
        $this->assertNotNull($latest);
        $this->assertSame('0.2.0', $latest->version);
        $this->assertSame('v0.2.0', $latest->tag);
        $this->assertSame("## Changes\n- one", $latest->notes);
        $this->assertSame('https://github.com/acme/app/releases/tag/v0.2.0', $latest->url);

        $this->publish([self::release('v0.2.0'), self::release('v0.3.0-rc.1', prerelease: true)]);
        $this->assertSame('0.3.0-rc.1', $this->source()->latest(true)?->version);
        $this->assertSame('0.2.0', $this->source()->latest()?->version);

        $this->publish([self::release('main')]);
        $this->assertNull($this->source()->latest());
    }

    public function testPrivateRepositoriesUseTheTokenAndTheApiForDownloads(): void
    {
        $this->publish([self::release('v1.0.0', '', [
            'app-1.0.0.zip'         => 'https://github.com/acme/app/releases/download/v1.0.0/app-1.0.0.zip',
            'app-1.0.0.zip.minisig' => 'https://github.com/acme/app/releases/download/v1.0.0/app-1.0.0.zip.minisig',
            'app-1.0.0.zip.sha256'  => 'https://github.com/acme/app/releases/download/v1.0.0/app-1.0.0.zip.sha256',
        ])]);

        $public = $this->source()->latest();
        $this->assertSame('https://github.com/acme/app/releases/download/v1.0.0/app-1.0.0.zip', $public?->archive()['url'] ?? null);
        $this->assertArrayNotHasKey('Authorization', $this->transport->sent[0]['headers']);

        $private = $this->source(token: 'ghp_secret')->latest();
        $archive = $private?->archive();
        $this->assertNotNull($archive);
        $this->assertSame('https://api.github.com/repos/acme/app/releases/assets/1', $archive['url']);
        $this->assertSame('Bearer ghp_secret', $this->transport->sent[1]['headers']['Authorization']);

        // The asset redirects to a storage host: the token is not sent there.
        $this->transport->routes['https://api.github.com/repos/acme/app/releases/assets/1'] = new OutboundResponse(302, ['location' => 'https://objects.githubusercontent.com/blob?sig=x'], '');
        $this->transport->routes['https://objects.githubusercontent.com/blob?sig=x']        = new OutboundResponse(200, [], 'ZIP');
        $this->assertSame('ZIP', $this->source(token: 'ghp_secret')->download($archive['url']));
        $sent = array_slice($this->transport->sent, -2);
        $this->assertSame('application/octet-stream', $sent[0]['headers']['Accept']);
        $this->assertSame('Bearer ghp_secret', $sent[0]['headers']['Authorization']);
        $this->assertArrayNotHasKey('Authorization', $sent[1]['headers']);
    }

    public function testGitlabReleases(): void
    {
        $url = 'https://gitlab.com/api/v4/projects/' . rawurlencode('group/app') . '/releases?per_page=50';
        $this->transport->routes[$url] = new OutboundResponse(200, [], (string) json_encode([
            ['tag_name' => 'v2.0.0', 'name' => 'Two', 'description' => 'Notes', 'released_at' => '2026-11-10T00:00:00Z', 'upcoming_release' => true],
            ['tag_name' => 'v1.4.0', 'name' => 'One four', 'description' => 'Notes', 'released_at' => '2026-11-01T00:00:00Z', '_links' => ['self' => 'https://gitlab.com/group/app/-/releases/v1.4.0'],
                'assets' => ['links' => [['name' => 'app.zip', 'url' => 'https://gitlab.com/x/app.zip', 'direct_asset_url' => 'https://gitlab.com/group/app/-/releases/v1.4.0/downloads/app.zip']]]],
        ]));

        $latest = $this->source('gitlab:group/app', 'glpat-x')->latest();
        $this->assertNotNull($latest);
        $this->assertSame('1.4.0', $latest->version);
        $this->assertSame(['app.zip' => 'https://gitlab.com/group/app/-/releases/v1.4.0/downloads/app.zip'], $latest->assets);
        $this->assertSame('glpat-x', $this->transport->sent[0]['headers']['PRIVATE-TOKEN']);
    }

    public function testErrorsAreReported(): void
    {
        foreach ([[404, lang('Updates.error.access', [404])], [500, lang('Updates.error.status', [500, 'api.github.com'])]] as [$status, $message]) {
            $this->transport->routes[self::RELEASES] = new OutboundResponse($status, [], '');

            try {
                $this->source()->latest();
                $this->fail('Should fail.');
            } catch (UpdateException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }

        $this->transport->routes[self::RELEASES] = new OutboundResponse(200, [], '{not json');
        $this->expectExceptionMessage(lang('Updates.error.response'));
        $this->source()->latest();
    }
}
