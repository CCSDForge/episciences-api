<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\AppConstants;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AppConstants.
 *
 * AppConstants centralises all configuration keys used across the application.
 * Changing a constant silently breaks many consumers; these tests act as a
 * regression guard against accidental renames or value changes.
 *
 * Covers:
 * - Scalar constants
 * - AVAILABLE_FILTERS array contents
 * - APP_CONST structure (uri_templates, normalizationContext groups, custom operation order)
 */
final class AppConstantsTest extends TestCase
{
    // ── Scalar constants ───────────────────────────────────────────────────────

    public function testMaximumItemsPerPage(): void
    {
        $this->assertSame(1000, AppConstants::MAXIMUM_ITEMS_PER_PAGE);
    }

    public function testDefaultItemPerPage(): void
    {
        $this->assertSame(30, AppConstants::DEFAULT_ITEM_PER_PAGE);
    }

    public function testBase64Constant(): void
    {
        $this->assertSame('base64', AppConstants::BASE_64);
    }

    public function testDefaultPrecision(): void
    {
        $this->assertSame(0, AppConstants::DEFAULT_PRECISION);
    }

    public function testRateDefaultPrecision(): void
    {
        $this->assertSame(2, AppConstants::RATE_DEFAULT_PRECISION);
    }

    public function testIsAppItem(): void
    {
        $this->assertSame('isAppItem', AppConstants::IS_APP_ITEM);
    }

    public function testIsAppCollection(): void
    {
        $this->assertSame('isAppCollection', AppConstants::IS_APP_COLLECTION);
    }

    public function testOrderAsc(): void
    {
        $this->assertSame('ASC', AppConstants::ORDER_ASC);
    }

    public function testOrderDesc(): void
    {
        $this->assertSame('DESC', AppConstants::ORDER_DESC);
    }

    public function testWithDetails(): void
    {
        $this->assertSame('withDetails', AppConstants::WITH_DETAILS);
    }

    public function testPaperStatus(): void
    {
        $this->assertSame('status', AppConstants::PAPER_STATUS);
    }

    public function testPaperFlag(): void
    {
        $this->assertSame('flag', AppConstants::PAPER_FLAG);
    }

    public function testStartAfterDate(): void
    {
        $this->assertSame('startAfterDate', AppConstants::START_AFTER_DATE);
    }

    public function testSubmissionDate(): void
    {
        $this->assertSame('submissionDate', AppConstants::SUBMISSION_DATE);
    }

    public function testYearParam(): void
    {
        $this->assertSame('year', AppConstants::YEAR_PARAM);
    }

    public function testFilterTypeExact(): void
    {
        $this->assertSame('exact', AppConstants::FILTER_TYPE_EXACT);
    }

    // ── Stats operation name constants ────────────────────────────────────────

    public function testStatsDashboardItem(): void
    {
        $this->assertSame('get_stats_dashboard_item', AppConstants::STATS_DASHBOARD_ITEM);
    }

    public function testStatsNbSubmissionsItem(): void
    {
        $this->assertSame('get_stats_nb_submissions_item', AppConstants::STATS_NB_SUBMISSIONS_ITEM);
    }

    public function testStatsDelaySubmissionAcceptance(): void
    {
        $this->assertSame('get_delay_between_submit_and_acceptance_item', AppConstants::STATS_DELAY_SUBMISSION_ACCEPTANCE);
    }

    public function testStatsDelaySubmissionPublication(): void
    {
        $this->assertSame('get_delay_between_submit_and_publication_item', AppConstants::STATS_DELAY_SUBMISSION_PUBLICATION);
    }

    public function testStatsNbUsers(): void
    {
        $this->assertSame('get_stats_nb_users_item', AppConstants::STATS_NB_USERS);
    }

    // ── AVAILABLE_FILTERS array ───────────────────────────────────────────────

    public function testAvailableFiltersIsArray(): void
    {
        $this->assertIsArray(AppConstants::AVAILABLE_FILTERS);
    }

    public function testAvailableFiltersContainsRvid(): void
    {
        $this->assertContains('rvid', AppConstants::AVAILABLE_FILTERS);
    }

    public function testAvailableFiltersContainsRepoid(): void
    {
        $this->assertContains('repoid', AppConstants::AVAILABLE_FILTERS);
    }

    public function testAvailableFiltersContainsStatus(): void
    {
        $this->assertContains('status', AppConstants::AVAILABLE_FILTERS);
    }

    public function testAvailableFiltersContainsYear(): void
    {
        $this->assertContains(AppConstants::YEAR_PARAM, AppConstants::AVAILABLE_FILTERS);
    }

    public function testAvailableFiltersContainsWithDetails(): void
    {
        $this->assertContains(AppConstants::WITH_DETAILS, AppConstants::AVAILABLE_FILTERS);
    }

    public function testAvailableFiltersHasEightEntries(): void
    {
        $this->assertCount(8, AppConstants::AVAILABLE_FILTERS);
    }

    // ── APP_CONST: custom_operations / items / review order ───────────────────

    /**
     * The order of items in APP_CONST['custom_operations']['items']['review']
     * is used as index-based operation name lookups in the Review entity.
     * These assertions protect against accidental reordering.
     */
    public function testReviewCustomOperationsFirstIsDashboard(): void
    {
        $this->assertSame(
            AppConstants::STATS_DASHBOARD_ITEM,
            AppConstants::APP_CONST['custom_operations']['items']['review'][0]
        );
    }

    public function testReviewCustomOperationsSecondIsNbSubmissions(): void
    {
        $this->assertSame(
            AppConstants::STATS_NB_SUBMISSIONS_ITEM,
            AppConstants::APP_CONST['custom_operations']['items']['review'][1]
        );
    }

    public function testReviewCustomOperationsThirdIsDelayAcceptance(): void
    {
        $this->assertSame(
            AppConstants::STATS_DELAY_SUBMISSION_ACCEPTANCE,
            AppConstants::APP_CONST['custom_operations']['items']['review'][2]
        );
    }

    public function testReviewCustomOperationsFourthIsDelayPublication(): void
    {
        $this->assertSame(
            AppConstants::STATS_DELAY_SUBMISSION_PUBLICATION,
            AppConstants::APP_CONST['custom_operations']['items']['review'][3]
        );
    }

    public function testReviewCustomOperationsFifthIsNbUsers(): void
    {
        $this->assertSame(
            AppConstants::STATS_NB_USERS,
            AppConstants::APP_CONST['custom_operations']['items']['review'][4]
        );
    }

    public function testReviewCustomOperationsHasFiveEntries(): void
    {
        $this->assertCount(5, AppConstants::APP_CONST['custom_operations']['items']['review']);
    }

    // ── APP_CONST: uri_templates ───────────────────────────────────────────────

    public function testDashboardUriTemplateContainsJournalsPath(): void
    {
        $uri = AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DASHBOARD_ITEM];
        $this->assertStringContainsString('/journals/', $uri);
        $this->assertStringContainsString('stats/dashboard', $uri);
    }

    public function testNbSubmissionsUriTemplateContainsJournalsPath(): void
    {
        $uri = AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_NB_SUBMISSIONS_ITEM];
        $this->assertStringContainsString('stats/nb-submissions', $uri);
    }

    public function testDelayAcceptanceUriTemplateContainsJournalsPath(): void
    {
        $uri = AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DELAY_SUBMISSION_ACCEPTANCE];
        $this->assertStringContainsString('delay-submission-acceptance', $uri);
    }

    public function testDelayPublicationUriTemplateContainsJournalsPath(): void
    {
        $uri = AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_DELAY_SUBMISSION_PUBLICATION];
        $this->assertStringContainsString('delay-submission-publication', $uri);
    }

    public function testNbUsersUriTemplateContainsJournalsPath(): void
    {
        $uri = AppConstants::APP_CONST['custom_operations']['uri_template'][AppConstants::STATS_NB_USERS];
        $this->assertStringContainsString('stats/nb-users', $uri);
    }

    public function testAllUriTemplatesContainCodeParameter(): void
    {
        foreach (AppConstants::APP_CONST['custom_operations']['uri_template'] as $key => $uri) {
            $this->assertStringContainsString('{code}', $uri, "URI template for '$key' must contain {code}");
        }
    }

    // ── APP_CONST: normalizationContext groups ────────────────────────────────

    public function testNormalizationGroupReviewItemRead(): void
    {
        $group = AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0];
        $this->assertSame('read:stats:Review', $group);
    }

    public function testNormalizationGroupPapersItemRead(): void
    {
        $group = AppConstants::APP_CONST['normalizationContext']['groups']['papers']['item']['read'][0];
        $this->assertSame('read:Paper', $group);
    }

    public function testNormalizationGroupPapersCollectionRead(): void
    {
        $group = AppConstants::APP_CONST['normalizationContext']['groups']['papers']['collection']['read'][0];
        $this->assertSame('read:Papers', $group);
    }

    public function testNormalizationGroupVolumeItemRead(): void
    {
        $group = AppConstants::APP_CONST['normalizationContext']['groups']['volume']['item']['read'][0];
        $this->assertSame('read:Volume', $group);
    }

    public function testNormalizationGroupVolumeCollectionRead(): void
    {
        $group = AppConstants::APP_CONST['normalizationContext']['groups']['volume']['collection']['read'][0];
        $this->assertSame('read:Volumes', $group);
    }

    public function testNormalizationGroupSectionItemRead(): void
    {
        $group = AppConstants::APP_CONST['normalizationContext']['groups']['section']['item']['read'][0];
        $this->assertSame('read:Section', $group);
    }

    public function testNormalizationGroupSectionCollectionRead(): void
    {
        $group = AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0];
        $this->assertSame('read:Sections', $group);
    }

    public function testNormalizationGroupUserItemRead(): void
    {
        $group = AppConstants::APP_CONST['normalizationContext']['groups']['user']['item']['read'][0];
        $this->assertSame('read:User', $group);
    }

    public function testNormalizationGroupUserCollectionRead(): void
    {
        $group = AppConstants::APP_CONST['normalizationContext']['groups']['user']['collection']['read'][0];
        $this->assertSame('read:Users', $group);
    }
}
