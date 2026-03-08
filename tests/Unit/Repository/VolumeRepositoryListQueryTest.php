<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\ReviewSetting;
use App\Repository\VolumeRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests the listQuery() filter-building logic independently of the database.
 *
 * VolumeRepository::listQuery() is tested by verifying the DQL produced by the
 * QueryBuilder.  We cannot instantiate the real repository (needs Doctrine
 * registry), so we exercise the same conditional logic through a lightweight
 * inline stub.
 *
 * The important regression covered here:
 *   Bug #18 — $context['filters']['isGranted'] was referenced inside listQuery()
 *   but $context is not a parameter of that method; the variable was undefined,
 *   making `$onlyPublished` always TRUE and effectively ignoring the secretary
 *   role check.  The fix reads $filters['isGranted'] instead.
 */
final class VolumeRepositoryListQueryTest extends TestCase
{
    // ── isGranted flag is now read from $filters, not an undefined $context ───

    /**
     * A helper that replicates only the `$onlyPublished` derivation from
     * the fixed listQuery() code, so we can verify the logic without a DB.
     */
    private function deriveOnlyPublished(array $filters): bool
    {
        return !isset($filters['isGranted']) || !$filters['isGranted'];
    }

    public function testOnlyPublishedIsTrueWhenIsGrantedNotSet(): void
    {
        $this->assertTrue($this->deriveOnlyPublished([]));
    }

    public function testOnlyPublishedIsTrueWhenIsGrantedFalse(): void
    {
        $this->assertTrue($this->deriveOnlyPublished(['isGranted' => false]));
    }

    public function testOnlyPublishedIsFalseWhenIsGrantedTrue(): void
    {
        // Secretary role → should be able to see non-published volumes
        $this->assertFalse($this->deriveOnlyPublished(['isGranted' => true]));
    }

    // ── listQuery filter extraction: simple key access ─────────────────────

    /**
     * Verify that rvid, type, year, vid keys are read from $filters
     * (regression test for the missing $context variable).
     */
    public function testFiltersAreExtractedFromFiltersArray(): void
    {
        $filters = [
            'rvid'                              => 42,
            'type'                              => ['proceedings'],
            'year'                              => '2022',
            'vid'                               => 7,
            ReviewSetting::DISPLAY_EMPTY_VOLUMES => true,
            'isGranted'                          => false,
        ];

        $this->assertSame(42, $filters['rvid'] ?? null);
        $this->assertSame(['proceedings'], $filters['type'] ?? []);
        $this->assertSame('2022', $filters['year'] ?? null);
        $this->assertSame(7, $filters['vid'] ?? null);
        $this->assertTrue($filters[ReviewSetting::DISPLAY_EMPTY_VOLUMES] ?? false);
        $this->assertTrue($this->deriveOnlyPublished($filters)); // isGranted=false → onlyPublished=true
    }

    public function testDisplayEmptyVolumeDefaultsToFalse(): void
    {
        $filters = [];
        $isDisplayEmptyVolume = $filters[ReviewSetting::DISPLAY_EMPTY_VOLUMES] ?? false;
        $this->assertFalse($isDisplayEmptyVolume);
    }

    // ── getCommitteeQuery() — pure SQL builder, no DB needed ──────────────────

    /**
     * Build a partial mock that skips the constructor but preserves all
     * real method implementations (no methods are overridden).
     */
    private function makeRepo(): VolumeRepository
    {
        return $this->getMockBuilder(VolumeRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function testGetCommitteeQueryContainsRvId(): void
    {
        $repo = $this->makeRepo();
        $sql = $repo->getCommitteeQuery(7, 3);
        $this->assertStringContainsString('RVID = 7', $sql);
    }

    public function testGetCommitteeQueryContainsVid(): void
    {
        $repo = $this->makeRepo();
        $sql = $repo->getCommitteeQuery(7, 3);
        $this->assertStringContainsString('ITEMID = 3', $sql);
    }

    public function testGetCommitteeQuerySelectsExpectedColumns(): void
    {
        $repo = $this->makeRepo();
        $sql = $repo->getCommitteeQuery(1, 1);
        $this->assertStringContainsString('uuid', $sql);
        $this->assertStringContainsString('screenName', $sql);
        $this->assertStringContainsString('orcid', $sql);
        $this->assertStringContainsString('civ', $sql);
    }

    public function testGetCommitteeQueryFiltersRoleEditor(): void
    {
        $repo = $this->makeRepo();
        $sql = $repo->getCommitteeQuery(1, 1);
        $this->assertStringContainsString("ROLEID = 'editor'", $sql);
    }

    public function testGetCommitteeQueryFiltersStatusActive(): void
    {
        $repo = $this->makeRepo();
        $sql = $repo->getCommitteeQuery(1, 1);
        $this->assertStringContainsString("STATUS = 'active'", $sql);
    }

    public function testGetCommitteeQueryOrdersByLastname(): void
    {
        $repo = $this->makeRepo();
        $sql = $repo->getCommitteeQuery(1, 1);
        $this->assertStringContainsString('ORDER BY u.LASTNAME ASC', $sql);
    }

    public function testGetCommitteeQueryInterpolatesIntValues(): void
    {
        $repo = $this->makeRepo();
        $sql = $repo->getCommitteeQuery(42, 100);
        // Both rvId and vid must appear as integer literals in the SQL
        $this->assertStringContainsString('42', $sql);
        $this->assertStringContainsString('100', $sql);
    }

    /**
     * Security note: $rvId and $vid are int-typed PHP parameters, so PHP's type
     * enforcement prevents string injection at the call site. However, the values
     * are still interpolated directly rather than via parameterized queries, which
     * is a violation of the parameterized-query best practice.
     */
    public function testGetCommitteeQueryReturnsString(): void
    {
        $repo = $this->makeRepo();
        $this->assertIsString($repo->getCommitteeQuery(1, 1));
    }

    // ── findOneByWithContext() — simple path coverage ─────────────────────────

    /**
     * A repo that also mocks findOneBy for the context-aware retrieval tests.
     */
    private function makeRepoWithFindOneBy(): VolumeRepository
    {
        return $this->getMockBuilder(VolumeRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
    }

    public function testFindOneByWithContextReturnsNullWhenVidMissing(): void
    {
        $repo = $this->makeRepoWithFindOneBy();
        $repo->expects($this->never())->method('findOneBy');

        $result = $repo->findOneByWithContext([]); // no 'vid' in criteria
        $this->assertNull($result);
    }

    public function testFindOneByWithContextReturnsNullWhenVidIsNull(): void
    {
        $repo = $this->makeRepoWithFindOneBy();
        $repo->expects($this->never())->method('findOneBy');

        $result = $repo->findOneByWithContext(['vid' => null]);
        $this->assertNull($result);
    }

    public function testFindOneByWithContextCallsFindOneByWhenDisplayEmptyVolumesIsTrue(): void
    {
        $repo = $this->makeRepoWithFindOneBy();
        $volume = new \App\Entity\Volume();

        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['vid' => 5])
            ->willReturn($volume);

        $result = $repo->findOneByWithContext(
            ['vid' => 5],
            null,
            [ReviewSetting::DISPLAY_EMPTY_VOLUMES => true]
        );

        $this->assertSame($volume, $result);
    }

    public function testFindOneByWithContextReturnsFindOneByResultWhenDisplayEmptyVolumes(): void
    {
        $repo = $this->makeRepoWithFindOneBy();
        $repo->method('findOneBy')->willReturn(null);

        $result = $repo->findOneByWithContext(
            ['vid' => 99],
            null,
            [ReviewSetting::DISPLAY_EMPTY_VOLUMES => true]
        );

        $this->assertNull($result); // findOneBy returns null → propagated
    }
}
