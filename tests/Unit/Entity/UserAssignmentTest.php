<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Paper;
use App\Entity\UserAssignment;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserAssignment entity.
 *
 * Covers:
 * - ITEM_* constants
 * - ROLE_* constants
 * - STATUS_* constants
 * - All getters and setters (rvid, itemid, item, uid, tmpUser, roleid, status,
 *   invitationId, when, deadline, papers)
 * - Fluent interface on all setters
 */
final class UserAssignmentTest extends TestCase
{
    private UserAssignment $assignment;

    protected function setUp(): void
    {
        $this->assignment = new UserAssignment();
    }

    // ── ITEM constants ────────────────────────────────────────────────────────

    public function testItemPaperConstant(): void
    {
        $this->assertSame('paper', UserAssignment::ITEM_PAPER);
    }

    public function testItemSectionConstant(): void
    {
        $this->assertSame('section', UserAssignment::ITEM_SECTION);
    }

    public function testItemVolumeConstant(): void
    {
        $this->assertSame('volume', UserAssignment::ITEM_VOLUME);
    }

    public function testItemConstantsAreDistinct(): void
    {
        $items = [UserAssignment::ITEM_PAPER, UserAssignment::ITEM_SECTION, UserAssignment::ITEM_VOLUME];
        $this->assertCount(3, array_unique($items));
    }

    // ── ROLE constants ────────────────────────────────────────────────────────

    public function testRoleReviewerConstant(): void
    {
        $this->assertSame('reviewer', UserAssignment::ROLE_REVIEWER);
    }

    public function testRoleEditorConstant(): void
    {
        $this->assertSame('editor', UserAssignment::ROLE_EDITOR);
    }

    public function testRoleCopyEditorConstant(): void
    {
        $this->assertSame('copyeditor', UserAssignment::ROLE_COPY_EDITOR);
    }

    public function testRoleCoAuthorConstant(): void
    {
        $this->assertSame('coauthor', UserAssignment::ROLE_CO_AUTHOR);
    }

    public function testRoleConstantsAreDistinct(): void
    {
        $roles = [
            UserAssignment::ROLE_REVIEWER,
            UserAssignment::ROLE_EDITOR,
            UserAssignment::ROLE_COPY_EDITOR,
            UserAssignment::ROLE_CO_AUTHOR,
        ];
        $this->assertCount(4, array_unique($roles));
    }

    // ── STATUS constants ──────────────────────────────────────────────────────

    public function testStatusPendingConstant(): void
    {
        $this->assertSame('pending', UserAssignment::STATUS_PENDING);
    }

    public function testStatusActiveConstant(): void
    {
        $this->assertSame('active', UserAssignment::STATUS_ACTIVE);
    }

    public function testStatusInactiveConstant(): void
    {
        $this->assertSame('inactive', UserAssignment::STATUS_INACTIVE);
    }

    public function testStatusExpiredConstant(): void
    {
        $this->assertSame('expired', UserAssignment::STATUS_EXPIRED);
    }

    public function testStatusCancelledConstant(): void
    {
        $this->assertSame('cancelled', UserAssignment::STATUS_CANCELLED);
    }

    public function testStatusDeclinedConstant(): void
    {
        $this->assertSame('declined', UserAssignment::STATUS_DECLINED);
    }

    public function testStatusConstantsAreDistinct(): void
    {
        $statuses = [
            UserAssignment::STATUS_PENDING,
            UserAssignment::STATUS_ACTIVE,
            UserAssignment::STATUS_INACTIVE,
            UserAssignment::STATUS_EXPIRED,
            UserAssignment::STATUS_CANCELLED,
            UserAssignment::STATUS_DECLINED,
        ];
        $this->assertCount(6, array_unique($statuses));
    }

    // ── setInvitationId / getInvitationId ─────────────────────────────────────

    public function testGetInvitationIdDefaultsToNull(): void
    {
        $this->assertNull($this->assignment->getInvitationId());
    }

    public function testSetInvitationIdReturnsSelf(): void
    {
        $result = $this->assignment->setInvitationId(99);
        $this->assertSame($this->assignment, $result);
    }

    public function testSetInvitationIdStoresValue(): void
    {
        $this->assignment->setInvitationId(42);
        $this->assertSame(42, $this->assignment->getInvitationId());
    }

    public function testSetInvitationIdWithNullResetsToNull(): void
    {
        $this->assignment->setInvitationId(1);
        $this->assignment->setInvitationId(null);
        $this->assertNull($this->assignment->getInvitationId());
    }

    // ── setRvid / getRvid ─────────────────────────────────────────────────────

    public function testSetRvidReturnsSelf(): void
    {
        $result = $this->assignment->setRvid(5);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetRvidReturnsSetValue(): void
    {
        $this->assignment->setRvid(10);
        $this->assertSame(10, $this->assignment->getRvid());
    }

    // ── setItemid / getItemid ─────────────────────────────────────────────────

    public function testSetItemidReturnsSelf(): void
    {
        $result = $this->assignment->setItemid(100);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetItemidReturnsSetValue(): void
    {
        $this->assignment->setItemid(200);
        $this->assertSame(200, $this->assignment->getItemid());
    }

    // ── setItem / getItem ─────────────────────────────────────────────────────

    public function testSetItemReturnsSelf(): void
    {
        $result = $this->assignment->setItem(UserAssignment::ITEM_PAPER);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetItemReturnsSetValue(): void
    {
        $this->assignment->setItem(UserAssignment::ITEM_SECTION);
        $this->assertSame(UserAssignment::ITEM_SECTION, $this->assignment->getItem());
    }

    // ── setUid / getUid ───────────────────────────────────────────────────────

    public function testSetUidReturnsSelf(): void
    {
        $result = $this->assignment->setUid(7);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetUidReturnsSetValue(): void
    {
        $this->assignment->setUid(77);
        $this->assertSame(77, $this->assignment->getUid());
    }

    // ── setTmpUser / getTmpUser ───────────────────────────────────────────────

    public function testSetTmpUserReturnsSelf(): void
    {
        $result = $this->assignment->setTmpUser(true);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetTmpUserTrueValue(): void
    {
        $this->assignment->setTmpUser(true);
        $this->assertTrue($this->assignment->getTmpUser());
    }

    public function testGetTmpUserFalseValue(): void
    {
        $this->assignment->setTmpUser(false);
        $this->assertFalse($this->assignment->getTmpUser());
    }

    // ── setRoleid / getRoleid ─────────────────────────────────────────────────

    public function testSetRoleidReturnsSelf(): void
    {
        $result = $this->assignment->setRoleid(UserAssignment::ROLE_REVIEWER);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetRoleidReturnsSetValue(): void
    {
        $this->assignment->setRoleid(UserAssignment::ROLE_EDITOR);
        $this->assertSame(UserAssignment::ROLE_EDITOR, $this->assignment->getRoleid());
    }

    // ── setStatus / getStatus ─────────────────────────────────────────────────

    public function testSetStatusReturnsSelf(): void
    {
        $result = $this->assignment->setStatus(UserAssignment::STATUS_ACTIVE);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetStatusReturnsSetValue(): void
    {
        $this->assignment->setStatus(UserAssignment::STATUS_PENDING);
        $this->assertSame(UserAssignment::STATUS_PENDING, $this->assignment->getStatus());
    }

    // ── setWhen / getWhen ─────────────────────────────────────────────────────

    public function testSetWhenReturnsSelf(): void
    {
        $dt = new DateTime('2023-06-01');
        $result = $this->assignment->setWhen($dt);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetWhenReturnsSetValue(): void
    {
        $dt = new DateTime('2023-01-15');
        $this->assignment->setWhen($dt);
        $this->assertSame($dt, $this->assignment->getWhen());
    }

    // ── setDeadline / getDeadline ─────────────────────────────────────────────

    public function testGetDeadlineDefaultsToNull(): void
    {
        $this->assertNull($this->assignment->getDeadline());
    }

    public function testSetDeadlineReturnsSelf(): void
    {
        $dt = new DateTime('2024-12-31');
        $result = $this->assignment->setDeadline($dt);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetDeadlineReturnsSetValue(): void
    {
        $dt = new DateTime('2024-06-30');
        $this->assignment->setDeadline($dt);
        $this->assertSame($dt, $this->assignment->getDeadline());
    }

    public function testSetDeadlineWithNullResetsToNull(): void
    {
        $this->assignment->setDeadline(new DateTime());
        $this->assignment->setDeadline(null);
        $this->assertNull($this->assignment->getDeadline());
    }

    // ── setPapers / getPapers ─────────────────────────────────────────────────

    public function testGetPapersDefaultsToNull(): void
    {
        $this->assertNull($this->assignment->getPapers());
    }

    public function testSetPapersReturnsSelf(): void
    {
        $paper = $this->createStub(Paper::class);
        $result = $this->assignment->setPapers($paper);
        $this->assertSame($this->assignment, $result);
    }

    public function testGetPapersReturnsSetValue(): void
    {
        $paper = $this->createStub(Paper::class);
        $this->assignment->setPapers($paper);
        $this->assertSame($paper, $this->assignment->getPapers());
    }

    public function testSetPapersWithNullResetsToNull(): void
    {
        $this->assignment->setPapers($this->createStub(Paper::class));
        $this->assignment->setPapers(null);
        $this->assertNull($this->assignment->getPapers());
    }
}
