<?php

namespace App\Tests\Unit\Filter;

use App\Filter\YearFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class YearFilterTest extends TestCase
{
    private YearFilter $filter;
    private ManagerRegistry $managerRegistry;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        $this->managerRegistry
            ->method('getManagerForClass')
            ->willReturn($this->entityManager);

        // Create the filter with properties configuration
        $properties = ['submissionDate' => null];
        $this->filter = new YearFilter($this->managerRegistry, null, $properties);
    }

    public function testGetDescriptionWithProperties(): void
    {
        $resourceClass = 'App\Entity\Paper';
        
        $description = $this->filter->getDescription($resourceClass);

        $this->assertIsArray($description);
        $this->assertArrayHasKey('submissionDate', $description);
        
        $fieldDescription = $description['submissionDate'];
        $this->assertEquals('submissionDate', $fieldDescription['property']);
        $this->assertEquals('int', $fieldDescription['type']);
        $this->assertFalse($fieldDescription['required']);
        $this->assertArrayHasKey('swagger', $fieldDescription);
        
        $swagger = $fieldDescription['swagger'];
        $this->assertEquals('Filter using the year of first submission!', $swagger['description']);
        $this->assertEquals('year', $swagger['name']);
        $this->assertEquals('custom filter', $swagger['type']);
    }

    public function testGetDescriptionWithMultipleProperties(): void
    {
        $properties = [
            'submissionDate' => null,
            'publicationDate' => null,
            'reviewDate' => null
        ];
        
        $filter = new YearFilter($this->managerRegistry, null, $properties);
        $description = $filter->getDescription('App\Entity\Paper');

        $this->assertCount(3, $description);
        $this->assertArrayHasKey('submissionDate', $description);
        $this->assertArrayHasKey('publicationDate', $description);
        $this->assertArrayHasKey('reviewDate', $description);

        foreach ($description as $property => $config) {
            $this->assertEquals($property, $config['property']);
            $this->assertEquals('int', $config['type']);
            $this->assertFalse($config['required']);
            $this->assertArrayHasKey('swagger', $config);
        }
    }

    public function testGetDescriptionWithNoProperties(): void
    {
        $filter = new YearFilter($this->managerRegistry, null, []);
        $description = $filter->getDescription('App\Entity\Paper');

        $this->assertEmpty($description);
    }

    public function testGetDescriptionWithNullProperties(): void
    {
        $filter = new YearFilter($this->managerRegistry, null, null);
        $description = $filter->getDescription('App\Entity\Paper');

        $this->assertEmpty($description);
    }

    public function testGetDescriptionStructureConsistency(): void
    {
        $properties = ['createdAt' => null];
        $filter = new YearFilter($this->managerRegistry, null, $properties);
        $description = $filter->getDescription('App\Entity\Test');

        $this->assertArrayHasKey('createdAt', $description);
        
        $fieldConfig = $description['createdAt'];
        
        // Required structure elements
        $this->assertArrayHasKey('property', $fieldConfig);
        $this->assertArrayHasKey('type', $fieldConfig);
        $this->assertArrayHasKey('required', $fieldConfig);
        $this->assertArrayHasKey('swagger', $fieldConfig);

        // Swagger structure
        $swagger = $fieldConfig['swagger'];
        $this->assertArrayHasKey('description', $swagger);
        $this->assertArrayHasKey('name', $swagger);
        $this->assertArrayHasKey('type', $swagger);
    }

    public function testSwaggerDocumentationConsistency(): void
    {
        $properties = ['startDate' => null, 'endDate' => null];
        $filter = new YearFilter($this->managerRegistry, null, $properties);
        $description = $filter->getDescription('App\Entity\Event');

        foreach ($description as $property => $config) {
            $swagger = $config['swagger'];
            
            // All properties should have the same swagger description and type
            $this->assertEquals('Filter using the year of first submission!', $swagger['description']);
            $this->assertEquals('year', $swagger['name']);
            $this->assertEquals('custom filter', $swagger['type']);
        }
    }

    public function testPropertyNamesArePreserved(): void
    {
        $properties = ['firstSubmission' => null, 'lastRevision' => null];
        $filter = new YearFilter($this->managerRegistry, null, $properties);
        $description = $filter->getDescription('App\Entity\Document');

        $this->assertArrayHasKey('firstSubmission', $description);
        $this->assertArrayHasKey('lastRevision', $description);
        
        $this->assertEquals('firstSubmission', $description['firstSubmission']['property']);
        $this->assertEquals('lastRevision', $description['lastRevision']['property']);
    }

    public function testTypeIsAlwaysInt(): void
    {
        $properties = ['date1' => null, 'date2' => 'strategy', 'date3' => []];
        $filter = new YearFilter($this->managerRegistry, null, $properties);
        $description = $filter->getDescription('App\Entity\Test');

        foreach ($description as $config) {
            $this->assertEquals('int', $config['type']);
        }
    }

    public function testRequiredIsAlwaysFalse(): void
    {
        $properties = ['mandatory_date' => 'required', 'optional_date' => null];
        $filter = new YearFilter($this->managerRegistry, null, $properties);
        $description = $filter->getDescription('App\Entity\Test');

        foreach ($description as $config) {
            $this->assertFalse($config['required']);
        }
    }
}