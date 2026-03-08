<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Review;
use App\Entity\ReviewSetting;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Review entity.
 *
 * Covers:
 * - Constants: TABLE, PORTAL_ID, STATUS_DISABLED, STATUS_ENABLED, URI_TEMPLATE
 * - Simple getters/setters: code, name, subtitle, status, piwikid, creation
 * - getSetting(): key lookup through settings collection, null fallback
 * - addSetting() / removeSetting()
 */
final class ReviewTest extends TestCase
{
    private Review $review;

    protected function setUp(): void
    {
        $this->review = new Review();
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testTableConstant(): void
    {
        $this->assertSame('REVIEW', Review::TABLE);
    }

    public function testPortalIdIsZero(): void
    {
        $this->assertSame(0, Review::PORTAL_ID);
    }

    public function testStatusDisabledIsZero(): void
    {
        $this->assertSame(0, Review::STATUS_DISABLED);
    }

    public function testStatusEnabledIsOne(): void
    {
        $this->assertSame(1, Review::STATUS_ENABLED);
    }

    public function testUriTemplate(): void
    {
        $this->assertSame('/journals/', Review::URI_TEMPLATE);
    }

    // ── setCode / getCode ─────────────────────────────────────────────────────

    public function testSetCodeReturnsSelf(): void
    {
        $result = $this->review->setCode('myjournal');
        $this->assertSame($this->review, $result);
    }

    public function testGetCodeReturnsSetValue(): void
    {
        $this->review->setCode('episciences');
        $this->assertSame('episciences', $this->review->getCode());
    }

    // ── setName / getName ─────────────────────────────────────────────────────

    public function testSetNameReturnsSelf(): void
    {
        $result = $this->review->setName('Episciences Journal');
        $this->assertSame($this->review, $result);
    }

    public function testGetNameReturnsSetValue(): void
    {
        $this->review->setName('Test Journal');
        $this->assertSame('Test Journal', $this->review->getName());
    }

    // ── setSubtitle / getSubtitle ─────────────────────────────────────────────

    public function testGetSubtitleDefaultsToEmptyString(): void
    {
        $this->assertSame('', $this->review->getSubtitle());
    }

    public function testSetSubtitleReturnsSelf(): void
    {
        $result = $this->review->setSubtitle('A subtitle');
        $this->assertSame($this->review, $result);
    }

    public function testGetSubtitleReturnsSetValue(): void
    {
        $this->review->setSubtitle('Open access journal');
        $this->assertSame('Open access journal', $this->review->getSubtitle());
    }

    public function testSetSubtitleWithNullReturnsEmptyStringFromGetter(): void
    {
        $this->review->setSubtitle('first');
        $this->review->setSubtitle(null);
        $this->assertSame('', $this->review->getSubtitle());
    }

    // ── setStatus / getStatus ─────────────────────────────────────────────────

    public function testSetStatusReturnsSelf(): void
    {
        $result = $this->review->setStatus(Review::STATUS_ENABLED);
        $this->assertSame($this->review, $result);
    }

    public function testGetStatusReturnsSetValue(): void
    {
        $this->review->setStatus(Review::STATUS_ENABLED);
        $this->assertSame(Review::STATUS_ENABLED, $this->review->getStatus());
    }

    public function testStatusDisabledValue(): void
    {
        $this->review->setStatus(Review::STATUS_DISABLED);
        $this->assertSame(0, $this->review->getStatus());
    }

    // ── setPiwikid / getPiwikid ───────────────────────────────────────────────

    public function testSetPiwikidReturnsSelf(): void
    {
        $result = $this->review->setPiwikid(123);
        $this->assertSame($this->review, $result);
    }

    public function testGetPiwikidReturnsSetValue(): void
    {
        $this->review->setPiwikid(456);
        $this->assertSame(456, $this->review->getPiwikid());
    }

    // ── setCreation / getCreation ─────────────────────────────────────────────

    public function testSetCreationReturnsSelf(): void
    {
        $dt = new DateTime('2023-01-15');
        $result = $this->review->setCreation($dt);
        $this->assertSame($this->review, $result);
    }

    public function testGetCreationReturnsSetValue(): void
    {
        $dt = new DateTime('2023-06-01');
        $this->review->setCreation($dt);
        $this->assertSame($dt, $this->review->getCreation());
    }

    // ── getSetting ────────────────────────────────────────────────────────────

    /**
     * getSetting() iterates the settings collection looking for a key match.
     * We test the logic by adding real ReviewSetting objects via addSetting().
     */
    public function testGetSettingReturnsNullWhenNoSettings(): void
    {
        $this->assertNull($this->review->getSetting('anyKey'));
    }

    public function testGetSettingReturnsMatchingValue(): void
    {
        $setting = $this->buildReviewSetting(ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE, '1');
        $this->review->addSetting($setting);

        $this->assertSame('1', $this->review->getSetting(ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE));
    }

    public function testGetSettingReturnsNullForUnknownKey(): void
    {
        $setting = $this->buildReviewSetting(ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE, '1');
        $this->review->addSetting($setting);

        $this->assertNull($this->review->getSetting('nonExistentKey'));
    }

    public function testGetSettingReturnsFirstMatchWhenMultipleSettings(): void
    {
        $s1 = $this->buildReviewSetting(ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE, 'yes');
        $s2 = $this->buildReviewSetting(ReviewSetting::DISPLAY_EMPTY_VOLUMES, 'no');
        $this->review->addSetting($s1);
        $this->review->addSetting($s2);

        $this->assertSame('yes', $this->review->getSetting(ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE));
        $this->assertSame('no', $this->review->getSetting(ReviewSetting::DISPLAY_EMPTY_VOLUMES));
    }

    public function testGetSettingReturnsNullValue(): void
    {
        $setting = $this->buildReviewSetting('nullable_key', null);
        $this->review->addSetting($setting);

        $this->assertNull($this->review->getSetting('nullable_key'));
    }

    // ── addSetting / removeSetting ────────────────────────────────────────────

    public function testAddSettingReturnsSelf(): void
    {
        $setting = $this->buildReviewSetting('key', 'value');
        $result = $this->review->addSetting($setting);
        $this->assertSame($this->review, $result);
    }

    public function testAddSettingDoesNotAddDuplicate(): void
    {
        $setting = $this->buildReviewSetting('key', 'value');
        $this->review->addSetting($setting);
        $this->review->addSetting($setting); // same instance — should not add twice

        $this->assertCount(1, $this->review->getSettings());
    }

    public function testRemoveSettingReturnsSelf(): void
    {
        $setting = $this->buildReviewSetting('key', 'value', new Review());
        $this->review->addSetting($setting);
        $result = $this->review->removeSetting($setting);
        $this->assertSame($this->review, $result);
    }

    public function testRemoveSettingRemovesElement(): void
    {
        // Use a different Review as the setting's owner so that removeSetting()
        // does NOT call $setting->setReview(null) (which would violate the
        // non-nullable type hint). The element is still removed from the collection.
        $setting = $this->buildReviewSetting('key', 'value', new Review());
        $this->review->addSetting($setting);
        $this->review->removeSetting($setting);
        $this->assertCount(0, $this->review->getSettings());
    }

    // ── getSettings initial state ─────────────────────────────────────────────

    public function testGetSettingsInitiallyEmpty(): void
    {
        $this->assertCount(0, $this->review->getSettings());
    }

    // ── ReviewSetting constants ───────────────────────────────────────────────

    public function testReviewSettingAllowBrowseAcceptedArticleConstant(): void
    {
        $this->assertSame('allowBrowseAcceptedDocuments', ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE);
    }

    public function testReviewSettingDisplayEmptyVolumesConstant(): void
    {
        $this->assertSame('displayEmptyVolumes', ReviewSetting::DISPLAY_EMPTY_VOLUMES);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildReviewSetting(string $key, ?string $value, ?Review $linkedReview = null): ReviewSetting
    {
        $mock = $this->createMock(ReviewSetting::class);
        $mock->method('getSetting')->willReturn($key);
        $mock->method('getValue')->willReturn($value);
        // By default return $this->review so addSetting's contains() check works.
        // Pass a different Review when the setReview(null) path must be avoided.
        $mock->method('getReview')->willReturn($linkedReview ?? $this->review);
        return $mock;
    }
}
