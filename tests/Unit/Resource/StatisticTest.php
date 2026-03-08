<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Resource\Statistic;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Statistic resource DTO.
 *
 * Covers constants, fluent setters, and nullable value types.
 */
final class StatisticTest extends TestCase
{
    private Statistic $statistic;

    protected function setUp(): void
    {
        $this->statistic = new Statistic();
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testEvalIndicatorsContainsMedianReviewsNumber(): void
    {
        $this->assertArrayHasKey('median-reviews-number_get', Statistic::EVAL_INDICATORS);
        $this->assertSame('median-reviews-number', Statistic::EVAL_INDICATORS['median-reviews-number_get']);
    }

    public function testEvalIndicatorsContainsReviewsRequested(): void
    {
        $this->assertArrayHasKey('reviews-requested_get', Statistic::EVAL_INDICATORS);
        $this->assertSame('reviews-requested', Statistic::EVAL_INDICATORS['reviews-requested_get']);
    }

    public function testEvalIndicatorsContainsReviewsReceived(): void
    {
        $this->assertArrayHasKey('reviews-received_get', Statistic::EVAL_INDICATORS);
        $this->assertSame('reviews-received', Statistic::EVAL_INDICATORS['reviews-received_get']);
    }

    public function testAvailablePublicationIndicatorsContainsNbSubmissions(): void
    {
        $this->assertArrayHasKey('nb-submissions_get', Statistic::AVAILABLE_PUBLICATION_INDICATORS);
        $this->assertSame('nb-submissions', Statistic::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get']);
    }

    public function testAvailablePublicationIndicatorsContainsAcceptanceRate(): void
    {
        $this->assertArrayHasKey('acceptance-rate_get', Statistic::AVAILABLE_PUBLICATION_INDICATORS);
        $this->assertSame('acceptance-rate', Statistic::AVAILABLE_PUBLICATION_INDICATORS['acceptance-rate_get']);
    }

    public function testAvailablePublicationIndicatorsContainsMedianSubmissionPublication(): void
    {
        $this->assertArrayHasKey('median-submission-publication_get', Statistic::AVAILABLE_PUBLICATION_INDICATORS);
        $this->assertSame('median-submission-publication', Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get']);
    }

    public function testAvailablePublicationIndicatorsContainsMedianSubmissionAcceptance(): void
    {
        $this->assertArrayHasKey('median-submission-acceptance_get', Statistic::AVAILABLE_PUBLICATION_INDICATORS);
        $this->assertSame('median-submission-acceptance', Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get']);
    }

    public function testAvailablePublicationIndicatorsContainsEvaluation(): void
    {
        $this->assertArrayHasKey('evaluation_get_collection', Statistic::AVAILABLE_PUBLICATION_INDICATORS);
        $this->assertSame('evaluation', Statistic::AVAILABLE_PUBLICATION_INDICATORS['evaluation_get_collection']);
    }

    public function testAvailablePublicationIndicatorsHasFiveEntries(): void
    {
        $this->assertCount(5, Statistic::AVAILABLE_PUBLICATION_INDICATORS);
    }

    public function testEvalIndicatorsHasThreeEntries(): void
    {
        $this->assertCount(3, Statistic::EVAL_INDICATORS);
    }

    // ── setName / getName ─────────────────────────────────────────────────────

    public function testSetAndGetName(): void
    {
        $result = $this->statistic->setName('nb-submissions');
        $this->assertSame($this->statistic, $result, 'setName() must return self for fluent interface');
        $this->assertSame('nb-submissions', $this->statistic->getName());
    }

    // ── setValue / getValue ───────────────────────────────────────────────────

    public function testSetFloatValue(): void
    {
        $this->statistic->setValue(3.14);
        $this->assertSame(3.14, $this->statistic->getValue());
    }

    public function testSetNullValue(): void
    {
        $this->statistic->setValue(null);
        $this->assertNull($this->statistic->getValue());
    }

    public function testSetArrayValue(): void
    {
        $data = ['key' => 'value', 'count' => 42];
        $this->statistic->setValue($data);
        $this->assertSame($data, $this->statistic->getValue());
    }

    public function testSetValueReturnsFluentInterface(): void
    {
        $result = $this->statistic->setName('test')->setValue(0.0);
        $this->assertSame($this->statistic, $result);
    }

    // ── setUnit / getUnit ─────────────────────────────────────────────────────

    public function testSetAndGetUnit(): void
    {
        $result = $this->statistic->setUnit('week');
        $this->assertSame($this->statistic, $result);
        $this->assertSame('week', $this->statistic->getUnit());
    }

    public function testSetNullUnit(): void
    {
        $this->statistic->setUnit(null);
        $this->assertNull($this->statistic->getUnit());
    }

    public function testDefaultUnitIsNull(): void
    {
        $this->assertNull($this->statistic->getUnit());
    }

    // ── Fluent chaining ───────────────────────────────────────────────────────

    public function testFluentChaining(): void
    {
        $result = $this->statistic
            ->setName('acceptance-rate')
            ->setValue(87.5)
            ->setUnit('%');

        $this->assertSame($this->statistic, $result);
        $this->assertSame('acceptance-rate', $this->statistic->getName());
        $this->assertSame(87.5, $this->statistic->getValue());
        $this->assertSame('%', $this->statistic->getUnit());
    }

    // ── Key lookup pattern used in StatisticStateProvider ────────────────────

    public function testEvalIndicatorKeyLookupByValue(): void
    {
        // StatisticStateProvider uses array_key_exists on AVAILABLE_PUBLICATION_INDICATORS
        $operationName = 'nb-submissions_get';
        $this->assertTrue(array_key_exists($operationName, Statistic::AVAILABLE_PUBLICATION_INDICATORS));
    }

    public function testEvalIndicatorInArrayCheck(): void
    {
        // StatisticStateProvider uses in_array() on EVAL_INDICATORS values
        $indicator = 'reviews-requested';
        $this->assertTrue(in_array($indicator, Statistic::EVAL_INDICATORS, true));
    }

    public function testUnknownIndicatorNotInArray(): void
    {
        $this->assertFalse(in_array('unknown-indicator', Statistic::EVAL_INDICATORS, true));
        $this->assertFalse(array_key_exists('unknown_get', Statistic::AVAILABLE_PUBLICATION_INDICATORS));
    }
}
