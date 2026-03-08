<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Resource\Facet;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Facet resource DTO.
 */
final class FacetTest extends TestCase
{
    private Facet $facet;

    protected function setUp(): void
    {
        $this->facet = new Facet();
    }

    // ── setField / getField ───────────────────────────────────────────────────

    public function testSetAndGetField(): void
    {
        $result = $this->facet->setField('authorLastNameFirstNamePrefixed_fs');
        $this->assertSame($this->facet, $result, 'setField() must return self');
        $this->assertSame('authorLastNameFirstNamePrefixed_fs', $this->facet->getField());
    }

    public function testSetEmptyField(): void
    {
        $this->facet->setField('');
        $this->assertSame('', $this->facet->getField());
    }

    // ── setValues / getValues ─────────────────────────────────────────────────

    public function testSetAndGetValues(): void
    {
        $values = ['name' => 'Dupont, Jean', 'count' => 5];
        $result = $this->facet->setValues($values);
        $this->assertSame($this->facet, $result, 'setValues() must return self');
        $this->assertSame($values, $this->facet->getValues());
    }

    public function testSetEmptyValues(): void
    {
        $this->facet->setValues([]);
        $this->assertSame([], $this->facet->getValues());
    }

    // ── Fluent chaining ───────────────────────────────────────────────────────

    public function testFluentChaining(): void
    {
        $values = ['name' => 'Martin, Pierre', 'count' => 3];
        $result = $this->facet
            ->setField('authorLastNameFirstNamePrefixed_fs')
            ->setValues($values);

        $this->assertSame($this->facet, $result);
        $this->assertSame('authorLastNameFirstNamePrefixed_fs', $this->facet->getField());
        $this->assertSame($values, $this->facet->getValues());
    }

    // ── Typical use-case: BrowseStateProvider pattern ─────────────────────────

    public function testTypicalBrowseStateProviderUsage(): void
    {
        $fieldName = 'authorLastNameFirstNamePrefixed_fs';
        $authorData = ['name' => 'Smith, John', 'count' => 12];

        $facet = (new Facet())
            ->setField($fieldName)
            ->setValues($authorData);

        $this->assertSame($fieldName, $facet->getField());
        $this->assertSame($authorData, $facet->getValues());
        $this->assertArrayHasKey('name', $facet->getValues());
        $this->assertArrayHasKey('count', $facet->getValues());
    }
}
