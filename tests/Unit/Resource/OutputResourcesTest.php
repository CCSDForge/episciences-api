<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Resource\AbstractStatResource;
use App\Resource\DashboardOutput;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Resource\UsersStatsOutput;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for the four empty AbstractStatResource subclasses.
 *
 * These classes have no additional logic; the tests guard against accidental
 * inheritance regressions and verify the fluent API inherited from
 * AbstractStatResource works correctly for each subtype.
 */
final class OutputResourcesTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // SubmissionOutput
    // ══════════════════════════════════════════════════════════════════════════

    public function testSubmissionOutputExtendsAbstractStatResource(): void
    {
        $this->assertInstanceOf(AbstractStatResource::class, new SubmissionOutput());
    }

    public function testSubmissionOutputSetDetailsReturnsSelf(): void
    {
        $r = new SubmissionOutput();
        $result = $r->setDetails(['key' => 'val']);
        $this->assertSame($r, $result);
    }

    public function testSubmissionOutputGetDetailsReturnsSetValue(): void
    {
        $r = new SubmissionOutput();
        $r->setDetails(['a' => 1]);
        $this->assertSame(['a' => 1], $r->getDetails());
    }

    public function testSubmissionOutputSetRequestedFiltersReturnsSelf(): void
    {
        $r = new SubmissionOutput();
        $result = $r->setRequestedFilters(['year' => '2023']);
        $this->assertSame($r, $result);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SubmissionAcceptanceDelayOutput
    // ══════════════════════════════════════════════════════════════════════════

    public function testSubmissionAcceptanceDelayOutputExtendsAbstractStatResource(): void
    {
        $this->assertInstanceOf(AbstractStatResource::class, new SubmissionAcceptanceDelayOutput());
    }

    public function testSubmissionAcceptanceDelayOutputSetDetailsReturnsSelf(): void
    {
        $r = new SubmissionAcceptanceDelayOutput();
        $result = $r->setDetails(['key' => 'val']);
        $this->assertSame($r, $result);
    }

    public function testSubmissionAcceptanceDelayOutputGetDetailsReturnsSetValue(): void
    {
        $r = new SubmissionAcceptanceDelayOutput();
        $r->setDetails(['delay' => 42]);
        $this->assertSame(['delay' => 42], $r->getDetails());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SubmissionPublicationDelayOutput
    // ══════════════════════════════════════════════════════════════════════════

    public function testSubmissionPublicationDelayOutputExtendsAbstractStatResource(): void
    {
        $this->assertInstanceOf(AbstractStatResource::class, new SubmissionPublicationDelayOutput());
    }

    public function testSubmissionPublicationDelayOutputSetDetailsReturnsSelf(): void
    {
        $r = new SubmissionPublicationDelayOutput();
        $result = $r->setDetails(['pub' => 'delay']);
        $this->assertSame($r, $result);
    }

    public function testSubmissionPublicationDelayOutputGetDetailsReturnsSetValue(): void
    {
        $r = new SubmissionPublicationDelayOutput();
        $r->setDetails(['days' => 90]);
        $this->assertSame(['days' => 90], $r->getDetails());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UsersStatsOutput
    // ══════════════════════════════════════════════════════════════════════════

    public function testUsersStatsOutputExtendsAbstractStatResource(): void
    {
        $this->assertInstanceOf(AbstractStatResource::class, new UsersStatsOutput());
    }

    public function testUsersStatsOutputSetDetailsReturnsSelf(): void
    {
        $r = new UsersStatsOutput();
        $result = $r->setDetails(['users' => 10]);
        $this->assertSame($r, $result);
    }

    public function testUsersStatsOutputGetDetailsReturnsSetValue(): void
    {
        $r = new UsersStatsOutput();
        $r->setDetails(['reviewers' => 5, 'editors' => 3]);
        $this->assertSame(['reviewers' => 5, 'editors' => 3], $r->getDetails());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DashboardOutput
    // ══════════════════════════════════════════════════════════════════════════

    public function testDashboardOutputExtendsAbstractStatResource(): void
    {
        $this->assertInstanceOf(AbstractStatResource::class, new DashboardOutput());
    }

    public function testDashboardOutputSetDetailsReturnsSelf(): void
    {
        $r = new DashboardOutput();
        $result = $r->setDetails(['total' => 100]);
        $this->assertSame($r, $result);
    }

    public function testDashboardOutputGetDetailsReturnsSetValue(): void
    {
        $r = new DashboardOutput();
        $r->setDetails(['papers' => 50, 'reviews' => 25]);
        $this->assertSame(['papers' => 50, 'reviews' => 25], $r->getDetails());
    }
}
