<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Paper;
use App\Entity\UserAssignment;
use PHPUnit\Framework\TestCase;

/**
 * The assignments of a paper grouped by role and by uid. A temporary user (reviewer invited without account) has an id
 * of another table than the uid of the users: its assignments are under the key "tmp_<id>" among the reviewers, as
 * Episciences_PapersManager::getReviewers() in the gpl.
 */
class PaperAssignmentsTest extends TestCase
{
    private function assignment(int $uid, string $role, bool $isTmpUser = false): UserAssignment
    {
        return new UserAssignment()
            ->setUid($uid)
            ->setRoleid($role)
            ->setTmpUser($isTmpUser)
            ->setStatus(UserAssignment::STATUS_ACTIVE)
            ->setWhen(new \DateTimeImmutable('2024-01-01'));
    }

    public function testATemporaryReviewerHasAKeyOfItsOwn(): void
    {
        $paper = new Paper();
        $reviewer = $this->assignment(70, UserAssignment::ROLE_REVIEWER);
        $paper->addAssignment($reviewer);
        // the same id as reviewer 70, but of a temporary user
        $temporary = $this->assignment(70, UserAssignment::ROLE_REVIEWER, true);
        $paper->addAssignment($temporary);
        $editor = $this->assignment(80, UserAssignment::ROLE_EDITOR);
        $paper->addAssignment($editor);

        $this->assertSame([70 => [$reviewer], 'tmp_70' => [$temporary]], $paper->getReviewers());
        $this->assertSame([80 => [$editor]], $paper->getEditors());
    }
}
