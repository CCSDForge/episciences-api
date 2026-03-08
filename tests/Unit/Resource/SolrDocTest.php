<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Resource\SolrDoc;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the SolrDoc resource.
 *
 * Covers:
 * - setOptions() dynamic dispatch via camelCase conversion
 * - setLanguageS() special handling of 'false' string → DEFAULT_LANGUAGE
 * - DEFAULT_LANGUAGE constant
 * - Individual setters/getters for key fields
 */
final class SolrDocTest extends TestCase
{
    // ── DEFAULT_LANGUAGE constant ─────────────────────────────────────────────

    public function testDefaultLanguageConstant(): void
    {
        $this->assertSame('en', SolrDoc::DEFAULT_LANGUAGE);
    }

    // ── setLanguageS: 'false' string guard ───────────────────────────────────

    public function testSetLanguageSStoredNormally(): void
    {
        $doc = new SolrDoc();
        $doc->setLanguageS('fr');
        $this->assertSame('fr', $doc->getLanguageS());
    }

    public function testSetLanguageSWithFalseStringFallsBackToDefault(): void
    {
        $doc = new SolrDoc();
        $doc->setLanguageS('false');
        $this->assertSame(SolrDoc::DEFAULT_LANGUAGE, $doc->getLanguageS());
    }

    public function testSetLanguageSWithEmptyStringStoredAsIs(): void
    {
        $doc = new SolrDoc();
        $doc->setLanguageS('');
        $this->assertSame('', $doc->getLanguageS());
    }

    public function testSetLanguageSDefaultParameterUsesDefaultLanguage(): void
    {
        $doc = new SolrDoc();
        $doc->setLanguageS();
        $this->assertSame(SolrDoc::DEFAULT_LANGUAGE, $doc->getLanguageS());
    }

    // ── setOptions: dynamic dispatch ──────────────────────────────────────────

    public function testSetOptionsWithDocidKey(): void
    {
        $doc = new SolrDoc(['docid' => 42]);
        $this->assertSame(42, $doc->getDocid());
    }

    public function testSetOptionsWithPaperidKey(): void
    {
        $doc = new SolrDoc(['paperid' => 100]);
        $this->assertSame(100, $doc->getPaperid());
    }

    public function testSetOptionsWithLanguageKey(): void
    {
        $doc = new SolrDoc(['language_s' => 'de']);
        $this->assertSame('de', $doc->getLanguageS());
    }

    public function testSetOptionsWithFalseLanguageIsNormalized(): void
    {
        $doc = new SolrDoc(['language_s' => 'false']);
        $this->assertSame(SolrDoc::DEFAULT_LANGUAGE, $doc->getLanguageS());
    }

    public function testSetOptionsIgnoresUnknownKeys(): void
    {
        // Should not throw; unknown keys have no matching setter
        $doc = new SolrDoc(['non_existent_field' => 'value']);
        $this->assertInstanceOf(SolrDoc::class, $doc);
    }

    public function testSetOptionsWithMultipleKeys(): void
    {
        $doc = new SolrDoc([
            'docid'      => 7,
            'paperid'    => 8,
            'language_s' => 'es',
        ]);

        $this->assertSame(7, $doc->getDocid());
        $this->assertSame(8, $doc->getPaperid());
        $this->assertSame('es', $doc->getLanguageS());
    }

    public function testSetOptionsWithArrayFields(): void
    {
        $doc = new SolrDoc([
            'author_fullname_s' => ['Dupont, Jean', 'Martin, Pierre'],
            'keyword_t'         => ['physics', 'math'],
        ]);

        $this->assertSame(['Dupont, Jean', 'Martin, Pierre'], $doc->getAuthorFullnameS());
        $this->assertSame(['physics', 'math'], $doc->getKeywordT());
    }

    public function testConstructorWithEmptyOptions(): void
    {
        $doc = new SolrDoc([]);
        $this->assertInstanceOf(SolrDoc::class, $doc);
    }

    public function testConstructorWithNoOptions(): void
    {
        $doc = new SolrDoc();
        $this->assertInstanceOf(SolrDoc::class, $doc);
    }

    // ── Individual getters/setters ────────────────────────────────────────────

    public function testSetAndGetDocid(): void
    {
        $doc = new SolrDoc();
        $doc->setDocid(999);
        $this->assertSame(999, $doc->getDocid());
    }

    public function testSetAndGetPaperid(): void
    {
        $doc = new SolrDoc();
        $doc->setPaperid(123);
        $this->assertSame(123, $doc->getPaperid());
    }

    public function testSetAndGetDoiS(): void
    {
        $doc = new SolrDoc();
        $doc->setDoiS('10.1234/test');
        $this->assertSame('10.1234/test', $doc->getDoiS());
    }

    public function testSetAndGetRevueIdI(): void
    {
        $doc = new SolrDoc();
        $doc->setRevueIdI(42);
        $this->assertSame(42, $doc->getRevueIdI());
    }

    public function testSetAndGetRevueCodeT(): void
    {
        $doc = new SolrDoc();
        $doc->setRevueCodeT('epijinfo');
        $this->assertSame('epijinfo', $doc->getRevueCodeT());
    }

    public function testSetAndGetPaperTitleT(): void
    {
        $doc = new SolrDoc();
        $doc->setPaperTitleT(['My Paper Title']);
        $this->assertSame(['My Paper Title'], $doc->getPaperTitleT());
    }

    public function testSetAndGetVersionTd(): void
    {
        $doc = new SolrDoc();
        $doc->setVersionTd(3);
        $this->assertSame(3, $doc->getVersionTd());
    }

    public function testSetAndGetSectionIdI(): void
    {
        $doc = new SolrDoc();
        $doc->setSectionIdI(5);
        $this->assertSame(5, $doc->getSectionIdI());
    }

    public function testSetAndGetVolumeIdI(): void
    {
        $doc = new SolrDoc();
        $doc->setVolumeIdI(10);
        $this->assertSame(10, $doc->getVolumeIdI());
    }

    public function testSetAndGetEsDocUrlS(): void
    {
        $doc = new SolrDoc();
        $doc->setEsDocUrlS('https://episciences.org/paper/123');
        $this->assertSame('https://episciences.org/paper/123', $doc->getEsDocUrlS());
    }

    // ── COMMON_GROUPS constant ────────────────────────────────────────────────

    public function testCommonGroupsContainsBrowseAuthors(): void
    {
        $this->assertContains('read:Browse:Authors:fullName', SolrDoc::COMMON_GROUPS);
    }

    public function testCommonGroupsContainsReadSearch(): void
    {
        $this->assertContains('read:Search', SolrDoc::COMMON_GROUPS);
    }
}
