<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Resource\DashboardOutput;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Resource\UsersStatsOutput;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbstractStatResource and all concrete subclasses.
 *
 * Concrete output classes (DashboardOutput, SubmissionOutput, etc.) extend
 * AbstractStatResource without adding anything, so they serve as test vehicles.
 *
 * Bug protected by tests:
 * - getRequestedFilters() removes the 'page' key if present (not yet exposed
 *   in the API); must NOT expose it to consumers.
 *
 * Covers:
 * - Default constructor values
 * - setAvailableFilters() / getAvailableFilters()
 * - setRequestedFilters() / getRequestedFilters() — including 'page' removal
 * - setName() / getName()
 * - setValue() / getValue() with int, float, array, null
 * - setDetails() / getDetails()
 * - __toString()
 * - All concrete output subclasses instantiate correctly
 */
final class AbstractStatResourceTest extends TestCase
{
    private DashboardOutput $resource;

    protected function setUp(): void
    {
        $this->resource = new DashboardOutput();
    }

    // ── Default constructor values ─────────────────────────────────────────────

    public function testDefaultAvailableFiltersIsEmptyArray(): void
    {
        $this->assertSame([], $this->resource->getAvailableFilters());
    }

    public function testDefaultRequestedFiltersIsEmptyArray(): void
    {
        $this->assertSame([], $this->resource->getRequestedFilters());
    }

    public function testDefaultNameIsEmptyString(): void
    {
        $this->assertSame('', $this->resource->getName());
    }

    public function testDefaultValueIsNull(): void
    {
        $this->assertNull($this->resource->getValue());
    }

    public function testDefaultDetailsIsNull(): void
    {
        $this->assertNull($this->resource->getDetails());
    }

    // ── setAvailableFilters / getAvailableFilters ──────────────────────────────

    public function testSetAvailableFiltersReturnsSelf(): void
    {
        $result = $this->resource->setAvailableFilters(['year', 'status']);
        $this->assertSame($this->resource, $result);
    }

    public function testGetAvailableFiltersReturnsSetValue(): void
    {
        $filters = ['year', 'status', 'withDetails'];
        $this->resource->setAvailableFilters($filters);
        $this->assertSame($filters, $this->resource->getAvailableFilters());
    }

    // ── setRequestedFilters / getRequestedFilters ──────────────────────────────

    public function testSetRequestedFiltersReturnsSelf(): void
    {
        $result = $this->resource->setRequestedFilters(['year' => '2023']);
        $this->assertSame($this->resource, $result);
    }

    public function testGetRequestedFiltersReturnsSetValue(): void
    {
        $this->resource->setRequestedFilters(['year' => '2023', 'status' => '1']);
        $this->assertSame(['year' => '2023', 'status' => '1'], $this->resource->getRequestedFilters());
    }

    /**
     * getRequestedFilters() must silently remove the 'page' key because
     * pagination is not yet available in stats endpoints.
     */
    public function testGetRequestedFiltersStripsPageKey(): void
    {
        $this->resource->setRequestedFilters(['year' => '2023', 'page' => '2']);
        $result = $this->resource->getRequestedFilters();

        $this->assertArrayNotHasKey('page', $result);
        $this->assertArrayHasKey('year', $result);
    }

    public function testGetRequestedFiltersWithOnlyPageReturnsEmpty(): void
    {
        $this->resource->setRequestedFilters(['page' => '3']);
        $this->assertSame([], $this->resource->getRequestedFilters());
    }

    public function testGetRequestedFiltersWithoutPageIsUnchanged(): void
    {
        $filters = ['year' => '2022', 'withDetails' => '1'];
        $this->resource->setRequestedFilters($filters);
        $this->assertSame($filters, $this->resource->getRequestedFilters());
    }

    // ── setName / getName ─────────────────────────────────────────────────────

    public function testSetNameReturnsSelf(): void
    {
        $result = $this->resource->setName('dashboard');
        $this->assertSame($this->resource, $result);
    }

    public function testGetNameReturnsSetValue(): void
    {
        $this->resource->setName('nb_submissions');
        $this->assertSame('nb_submissions', $this->resource->getName());
    }

    // ── setValue / getValue ───────────────────────────────────────────────────

    public function testSetValueReturnsSelf(): void
    {
        $result = $this->resource->setValue(42);
        $this->assertSame($this->resource, $result);
    }

    public function testGetValueWithInt(): void
    {
        $this->resource->setValue(100);
        $this->assertSame(100, $this->resource->getValue());
    }

    public function testGetValueWithFloat(): void
    {
        $this->resource->setValue(3.14);
        $this->assertSame(3.14, $this->resource->getValue());
    }

    public function testGetValueWithArray(): void
    {
        $data = ['total' => 50, 'accepted' => 20];
        $this->resource->setValue($data);
        $this->assertSame($data, $this->resource->getValue());
    }

    public function testGetValueWithNull(): void
    {
        $this->resource->setValue(42);
        $this->resource->setValue(null);
        $this->assertNull($this->resource->getValue());
    }

    // ── setDetails / getDetails ───────────────────────────────────────────────

    public function testSetDetailsReturnsSelf(): void
    {
        $result = $this->resource->setDetails(['breakdown' => [1, 2, 3]]);
        $this->assertSame($this->resource, $result);
    }

    public function testGetDetailsReturnsSetValue(): void
    {
        $details = ['2023' => 50, '2022' => 30];
        $this->resource->setDetails($details);
        $this->assertSame($details, $this->resource->getDetails());
    }

    public function testSetDetailsWithNullResetsToNull(): void
    {
        $this->resource->setDetails(['data' => 1]);
        $this->resource->setDetails(null);
        $this->assertNull($this->resource->getDetails());
    }

    // ── __toString ────────────────────────────────────────────────────────────

    public function testToStringContainsResourceName(): void
    {
        $this->resource->setName('dashboard_stats');
        $this->assertStringContainsString('dashboard_stats', (string)$this->resource);
    }

    public function testToStringContainsFiltersJson(): void
    {
        $this->resource->setName('stats');
        $this->resource->setRequestedFilters(['year' => '2023']);
        $str = (string)$this->resource;
        $this->assertStringContainsString('2023', $str);
    }

    public function testToStringWithEmptyFilters(): void
    {
        $this->resource->setName('test');
        $str = (string)$this->resource;
        $this->assertStringContainsString('test', $str);
        $this->assertStringContainsString('[]', $str); // empty JSON array
    }

    public function testToStringDoesNotExposePageFilter(): void
    {
        $this->resource->setName('stats');
        $this->resource->setRequestedFilters(['page' => '2', 'year' => '2022']);
        $str = (string)$this->resource;
        // __toString uses getRequestedFilters() which strips 'page'
        $this->assertStringNotContainsString('"page"', $str);
        $this->assertStringContainsString('"year"', $str);
    }

    // ── Concrete subclasses instantiate correctly ──────────────────────────────

    public function testDashboardOutputInstantiates(): void
    {
        $this->assertInstanceOf(DashboardOutput::class, new DashboardOutput());
    }

    public function testSubmissionOutputInstantiates(): void
    {
        $this->assertInstanceOf(SubmissionOutput::class, new SubmissionOutput());
    }

    public function testSubmissionAcceptanceDelayOutputInstantiates(): void
    {
        $this->assertInstanceOf(SubmissionAcceptanceDelayOutput::class, new SubmissionAcceptanceDelayOutput());
    }

    public function testSubmissionPublicationDelayOutputInstantiates(): void
    {
        $this->assertInstanceOf(SubmissionPublicationDelayOutput::class, new SubmissionPublicationDelayOutput());
    }

    public function testUsersStatsOutputInstantiates(): void
    {
        $this->assertInstanceOf(UsersStatsOutput::class, new UsersStatsOutput());
    }

    public function testAllOutputClassesExtendDashboardOutput(): void
    {
        // All concrete classes extend AbstractStatResource — verify via DashboardOutput sibling
        $classes = [
            SubmissionOutput::class,
            SubmissionAcceptanceDelayOutput::class,
            SubmissionPublicationDelayOutput::class,
            UsersStatsOutput::class,
        ];
        foreach ($classes as $class) {
            $obj = new $class();
            $this->assertSame('', $obj->getName(), "$class should default name to ''");
            $this->assertNull($obj->getValue(), "$class should default value to null");
        }
    }
}
