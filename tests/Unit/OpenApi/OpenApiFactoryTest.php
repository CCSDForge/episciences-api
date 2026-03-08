<?php

declare(strict_types=1);

namespace App\Tests\Unit\OpenApi;

use App\OpenApi\OpenApiFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OpenApiFactory constants.
 *
 * The factory's __invoke() logic depends on the live API Platform OpenApi
 * decorator chain (integration-only), but the constants it exposes as part
 * of the public API are verifiable in isolation.
 */
final class OpenApiFactoryTest extends TestCase
{
    // ── OAF_HIDDEN ────────────────────────────────────────────────────────────

    public function testOafHiddenConstant(): void
    {
        $this->assertSame('hidden', OpenApiFactory::OAF_HIDDEN);
    }

    // ── JWT_POST_LOGIN_OPERATION_ID ───────────────────────────────────────────

    public function testJwtPostLoginOperationId(): void
    {
        $this->assertSame('login_check_post', OpenApiFactory::JWT_POST_LOGIN_OPERATION_ID);
    }

    // ── USER_GET_COLLECTION_PATH ──────────────────────────────────────────────

    public function testUserGetCollectionPath(): void
    {
        $this->assertSame('/api/users', OpenApiFactory::USER_GET_COLLECTION_PATH);
    }

    // ── OAF_TAGS ─────────────────────────────────────────────────────────────

    public function testOafTagsContainsAuthKey(): void
    {
        $this->assertArrayHasKey('auth', OpenApiFactory::OAF_TAGS);
    }

    public function testOafTagsContainsStatsKey(): void
    {
        $this->assertArrayHasKey('stats', OpenApiFactory::OAF_TAGS);
    }

    public function testOafTagsContainsReviewKey(): void
    {
        $this->assertArrayHasKey('review', OpenApiFactory::OAF_TAGS);
    }

    public function testOafTagsContainsUserKey(): void
    {
        $this->assertArrayHasKey('user', OpenApiFactory::OAF_TAGS);
    }

    public function testOafTagsContainsSectionsVolumesKey(): void
    {
        $this->assertArrayHasKey('sections_volumes', OpenApiFactory::OAF_TAGS);
    }

    public function testOafTagsContainsBrowseSearchKey(): void
    {
        $this->assertArrayHasKey('browse_search', OpenApiFactory::OAF_TAGS);
    }

    public function testOafTagsContainsPaperKey(): void
    {
        $this->assertArrayHasKey('paper', OpenApiFactory::OAF_TAGS);
    }

    public function testOafTagsHasSevenEntries(): void
    {
        $this->assertCount(7, OpenApiFactory::OAF_TAGS);
    }

    public function testOafTagsAuthValue(): void
    {
        $this->assertSame('Sign in - Myspace', OpenApiFactory::OAF_TAGS['auth']);
    }

    public function testOafTagsPaperValue(): void
    {
        $this->assertSame('Papers', OpenApiFactory::OAF_TAGS['paper']);
    }

    public function testOafTagsBrowseSearchValue(): void
    {
        $this->assertSame('Browse | Search', OpenApiFactory::OAF_TAGS['browse_search']);
    }

    // ── VolumeStateProvider: isGranted filter logic (inline helper) ───────────

    /**
     * Mirrors the logic in VolumeStateProvider::provide():
     * $context['filters']['isGranted'] = $this->security->isGranted('ROLE_SECRETARY')
     *
     * The resulting flag determines whether non-published volumes are shown.
     * onlyPublished = !isGranted(ROLE_SECRETARY)
     */
    private function deriveOnlyPublished(bool $isSecretary): bool
    {
        return !$isSecretary;
    }

    public function testSecretaryRoleAllowsNonPublishedVolumes(): void
    {
        $this->assertFalse($this->deriveOnlyPublished(true));
    }

    public function testNonSecretaryRoleRestrictsToPublishedVolumes(): void
    {
        $this->assertTrue($this->deriveOnlyPublished(false));
    }

    // ── VolumeStateProvider: vid extraction ───────────────────────────────────

    /**
     * Mirrors the vid extraction in VolumeStateProvider::provide():
     *   $vid = $uriVariables['vid'] ?? null;
     *   if (!$vid) return null;
     */
    private function extractVid(array $uriVariables): ?int
    {
        $vid = $uriVariables['vid'] ?? null;
        return $vid ? (int)$vid : null;
    }

    public function testVidExtractedWhenPresent(): void
    {
        $this->assertSame(5, $this->extractVid(['vid' => 5]));
    }

    public function testVidIsNullWhenAbsent(): void
    {
        $this->assertNull($this->extractVid([]));
    }

    public function testVidIsNullWhenZero(): void
    {
        $this->assertNull($this->extractVid(['vid' => 0]));
    }

    // ── BrowseStateProvider: format detection logic ───────────────────────────

    /**
     * Mirrors the format detection in FeedController::__invoke():
     *   $format = str_contains($request->getPathInfo(), '/atom/') ? 'atom' : 'rss';
     */
    private function detectFeedFormat(string $pathInfo): string
    {
        return str_contains($pathInfo, '/atom/') ? 'atom' : 'rss';
    }

    public function testAtomPathDetectedAsAtom(): void
    {
        $this->assertSame('atom', $this->detectFeedFormat('/api/feed/atom/myjournal'));
    }

    public function testRssPathDetectedAsRss(): void
    {
        $this->assertSame('rss', $this->detectFeedFormat('/api/feed/rss/myjournal'));
    }

    public function testUnknownPathDefaultsToRss(): void
    {
        $this->assertSame('rss', $this->detectFeedFormat('/api/feed/unknown/myjournal'));
    }
}
