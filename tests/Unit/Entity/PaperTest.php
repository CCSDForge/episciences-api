<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Paper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Paper entity business logic.
 *
 * Bug fixed:
 *   isAccepted() compared getStatus() (int) with STATUS_ACCEPTED (array) using ===.
 *   An int can never strictly equal an array, so the method always returned false
 *   regardless of the paper's actual status.
 *   Fix: replaced `=== self::STATUS_ACCEPTED` with `in_array(..., true)`.
 *
 * Methods covered:
 * - isPublished()
 * - isAccepted() (bug regression)
 * - isStrictlyAccepted()
 * - getStatusDictionaryLabel()
 * - getUsersAllowedToEditPaperCitations()
 * - STATUS_DICTIONARY integrity
 * - STATUS_ACCEPTED array contents
 */
final class PaperTest extends TestCase
{
    private Paper $paper;

    protected function setUp(): void
    {
        $this->paper = new Paper();
    }

    // ── isPublished ───────────────────────────────────────────────────────────

    public function testIsPublishedReturnsTrueWhenStatusIsPublished(): void
    {
        $this->paper->setStatus(Paper::STATUS_PUBLISHED);
        $this->assertTrue($this->paper->isPublished());
    }

    public function testIsPublishedReturnsFalseWhenStatusIsNotPublished(): void
    {
        $this->paper->setStatus(Paper::STATUS_SUBMITTED);
        $this->assertFalse($this->paper->isPublished());
    }

    public function testIsPublishedReturnsFalseForRefusedStatus(): void
    {
        $this->paper->setStatus(Paper::STATUS_REFUSED);
        $this->assertFalse($this->paper->isPublished());
    }

    // ── isStrictlyAccepted ────────────────────────────────────────────────────

    public function testIsStrictlyAcceptedReturnsTrueWhenStatusIsStrictlyAccepted(): void
    {
        $this->paper->setStatus(Paper::STATUS_STRICTLY_ACCEPTED);
        $this->assertTrue($this->paper->isStrictlyAccepted());
    }

    public function testIsStrictlyAcceptedReturnsFalseForSubmitted(): void
    {
        $this->paper->setStatus(Paper::STATUS_SUBMITTED);
        $this->assertFalse($this->paper->isStrictlyAccepted());
    }

    // ── isAccepted (bug regression) ───────────────────────────────────────────

    /**
     * Bug: STATUS_ACCEPTED is an ARRAY but isAccepted() previously used ===
     * (strict equality), which compares int to array — always false.
     * The fix uses in_array() to check if the status is among the accepted values.
     */
    public function testIsAcceptedReturnsTrueForStrictlyAccepted(): void
    {
        $this->paper->setStatus(Paper::STATUS_STRICTLY_ACCEPTED);
        $this->assertTrue($this->paper->isAccepted(), 'STATUS_STRICTLY_ACCEPTED must be in STATUS_ACCEPTED');
    }

    public function testIsAcceptedReturnsTrueForCeWaitingForAuthorSources(): void
    {
        $this->paper->setStatus(Paper::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES);
        $this->assertTrue($this->paper->isAccepted());
    }

    public function testIsAcceptedReturnsTrueForAcceptedWaitingForAuthorFinalVersion(): void
    {
        $this->paper->setStatus(Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION);
        $this->assertTrue($this->paper->isAccepted());
    }

    public function testIsAcceptedReturnsTrueForReadyToPublish(): void
    {
        $this->paper->setStatus(Paper::STATUS_CE_READY_TO_PUBLISH);
        $this->assertTrue($this->paper->isAccepted());
    }

    public function testIsAcceptedReturnsFalseForSubmitted(): void
    {
        $this->paper->setStatus(Paper::STATUS_SUBMITTED);
        $this->assertFalse($this->paper->isAccepted(), 'Submitted papers must not be considered accepted');
    }

    public function testIsAcceptedReturnsFalseForRefused(): void
    {
        $this->paper->setStatus(Paper::STATUS_REFUSED);
        $this->assertFalse($this->paper->isAccepted());
    }

    public function testIsAcceptedReturnsFalseForPublished(): void
    {
        // Published is a different status from accepted
        $this->paper->setStatus(Paper::STATUS_PUBLISHED);
        $this->assertFalse($this->paper->isAccepted());
    }

    public function testIsAcceptedReturnsFalseForDeleted(): void
    {
        $this->paper->setStatus(Paper::STATUS_DELETED);
        $this->assertFalse($this->paper->isAccepted());
    }

    public function testIsAcceptedReturnsTrueForAllStatusAcceptedValues(): void
    {
        foreach (Paper::STATUS_ACCEPTED as $status) {
            $this->paper->setStatus($status);
            $this->assertTrue(
                $this->paper->isAccepted(),
                sprintf('Status %d should be accepted but isAccepted() returned false', $status)
            );
        }
    }

    // ── STATUS_ACCEPTED array integrity ───────────────────────────────────────

    public function testStatusAcceptedIsAnArray(): void
    {
        $this->assertIsArray(Paper::STATUS_ACCEPTED);
    }

    public function testStatusAcceptedContainsStrictlyAccepted(): void
    {
        $this->assertContains(Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_ACCEPTED);
    }

    public function testStatusAcceptedDoesNotContainSubmitted(): void
    {
        $this->assertNotContains(Paper::STATUS_SUBMITTED, Paper::STATUS_ACCEPTED);
    }

    public function testStatusAcceptedDoesNotContainRefused(): void
    {
        $this->assertNotContains(Paper::STATUS_REFUSED, Paper::STATUS_ACCEPTED);
    }

    public function testStatusAcceptedDoesNotContainPublished(): void
    {
        $this->assertNotContains(Paper::STATUS_PUBLISHED, Paper::STATUS_ACCEPTED);
    }

    public function testStatusAcceptedHasFifteenEntries(): void
    {
        $this->assertCount(15, Paper::STATUS_ACCEPTED);
    }

    // ── getStatusDictionaryLabel ──────────────────────────────────────────────

    public function testGetStatusDictionaryLabelForSubmitted(): void
    {
        $this->paper->setStatus(Paper::STATUS_SUBMITTED);
        $this->assertSame('submitted', $this->paper->getStatusDictionaryLabel());
    }

    public function testGetStatusDictionaryLabelForPublished(): void
    {
        $this->paper->setStatus(Paper::STATUS_PUBLISHED);
        $this->assertSame('published', $this->paper->getStatusDictionaryLabel());
    }

    public function testGetStatusDictionaryLabelForRefused(): void
    {
        $this->paper->setStatus(Paper::STATUS_REFUSED);
        $this->assertSame('refused', $this->paper->getStatusDictionaryLabel());
    }

    public function testGetStatusDictionaryLabelFallbackForUnknownStatus(): void
    {
        $this->paper->setStatus(9999);
        $this->assertSame('status_label_not_found', $this->paper->getStatusDictionaryLabel());
    }

    // ── STATUS_DICTIONARY integrity ───────────────────────────────────────────

    public function testStatusDictionaryMapsAllStatusConstants(): void
    {
        $this->assertArrayHasKey(Paper::STATUS_SUBMITTED, Paper::STATUS_DICTIONARY);
        $this->assertArrayHasKey(Paper::STATUS_PUBLISHED, Paper::STATUS_DICTIONARY);
        $this->assertArrayHasKey(Paper::STATUS_REFUSED, Paper::STATUS_DICTIONARY);
        $this->assertArrayHasKey(Paper::STATUS_DELETED, Paper::STATUS_DICTIONARY);
        $this->assertArrayHasKey(Paper::STATUS_REMOVED, Paper::STATUS_DICTIONARY);
        $this->assertArrayHasKey(Paper::STATUS_OBSOLETE, Paper::STATUS_DICTIONARY);
    }

    public function testStatusDictionaryValuesAreAllStrings(): void
    {
        foreach (Paper::STATUS_DICTIONARY as $label) {
            $this->assertIsString($label, "All status dictionary values must be strings");
        }
    }

    // ── getUsersAllowedToEditPaperCitations ───────────────────────────────────

    public function testGetUsersAllowedToEditPaperCitationsIncludesOwner(): void
    {
        $this->paper->setUid(42);
        $this->paper->setEditors([]);
        $this->paper->setCopyEditors([]);
        $this->paper->setCoAuthors([]);

        $allowed = $this->paper->getUsersAllowedToEditPaperCitations();
        $this->assertContains(42, $allowed);
    }

    public function testGetUsersAllowedToEditPaperCitationsIncludesCoAuthors(): void
    {
        $this->paper->setUid(1);
        $this->paper->setEditors([]);
        $this->paper->setCopyEditors([]);
        $this->paper->setCoAuthors([10 => 'data', 11 => 'data']);

        $allowed = $this->paper->getUsersAllowedToEditPaperCitations();
        $this->assertContains(10, $allowed);
        $this->assertContains(11, $allowed);
    }

    public function testGetUsersAllowedToEditPaperCitationsIncludesEditors(): void
    {
        $this->paper->setUid(1);
        $this->paper->setEditors([20 => 'data', 21 => 'data']);
        $this->paper->setCopyEditors([-1 => 'dummy']); // non-empty to prevent assignmentsProcess() reset
        $this->paper->setCoAuthors([-1 => 'dummy']); // non-empty to prevent assignmentsProcess() reset

        $allowed = $this->paper->getUsersAllowedToEditPaperCitations();
        $this->assertContains(20, $allowed);
        $this->assertContains(21, $allowed);
    }

    public function testGetUsersAllowedToEditPaperCitationsIncludesCopyEditors(): void
    {
        $this->paper->setUid(1);
        $this->paper->setEditors([-1 => 'dummy']); // non-empty to prevent assignmentsProcess() reset
        $this->paper->setCopyEditors([30 => 'data']);
        $this->paper->setCoAuthors([-1 => 'dummy']); // non-empty to prevent assignmentsProcess() reset

        $allowed = $this->paper->getUsersAllowedToEditPaperCitations();
        $this->assertContains(30, $allowed);
    }

    public function testGetUsersAllowedToEditPaperCitationsMergesAllRoles(): void
    {
        $this->paper->setUid(1);
        $this->paper->setEditors([20 => 'e']);
        $this->paper->setCopyEditors([30 => 'ce']);
        $this->paper->setCoAuthors([40 => 'ca']);

        $allowed = $this->paper->getUsersAllowedToEditPaperCitations();
        $this->assertContains(1, $allowed);
        $this->assertContains(20, $allowed);
        $this->assertContains(30, $allowed);
        $this->assertContains(40, $allowed);
        $this->assertCount(4, $allowed);
    }

    // ── Status constants ──────────────────────────────────────────────────────

    public function testStatusSubmittedIsZero(): void
    {
        $this->assertSame(0, Paper::STATUS_SUBMITTED);
    }

    public function testStatusPublishedIs16(): void
    {
        $this->assertSame(16, Paper::STATUS_PUBLISHED);
    }

    public function testStatusRefusedIs5(): void
    {
        $this->assertSame(5, Paper::STATUS_REFUSED);
    }

    public function testStatusDeletedIs12(): void
    {
        $this->assertSame(12, Paper::STATUS_DELETED);
    }

    public function testStatusRemovedIs13(): void
    {
        $this->assertSame(13, Paper::STATUS_REMOVED);
    }

    public function testStatusObsoleteIs6(): void
    {
        $this->assertSame(6, Paper::STATUS_OBSOLETE);
    }

    public function testStatusStrictlyAcceptedIs4(): void
    {
        $this->assertSame(4, Paper::STATUS_STRICTLY_ACCEPTED);
    }

    // ── AppConstants ──────────────────────────────────────────────────────────

    public function testUriTemplate(): void
    {
        $this->assertSame('/papers/', Paper::URI_TEMPLATE);
    }

    public function testTable(): void
    {
        $this->assertSame('PAPERS', Paper::TABLE);
    }
}
