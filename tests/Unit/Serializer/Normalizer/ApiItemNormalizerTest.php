<?php

namespace App\Tests\Unit\Serializer\Normalizer;

use App\Repository\PaperConflictsRepository;
use App\Resource\Statistic;
use App\Serializer\Normalizer\ApiItemNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiItemNormalizerTest extends TestCase
{
    public static function statisticIriProvider(): iterable
    {
        yield 'publication indicator' => ['median-submission-publication', '/api/statistics/median-submission-publication'];
        yield 'evaluation' => ['evaluation', '/api/statistics/evaluation'];
        yield 'evaluation indicator' => ['reviews-received', '/api/statistics/evaluation?indicator=reviews-received'];
        yield 'without operation' => ['nb-submissions-details', '/api/statistics/#nb-submissions-details'];
    }

    /**
     * Without an identifier, API Platform gives every statistic the IRI of the first item operation.
     */
    #[DataProvider('statisticIriProvider')]
    public function testStatisticIriMatchesTheIndicator(string $name, string $expectedIri): void
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(['@id' => '/api/statistics/nb-submissions', 'name' => $name]);

        $normalizer = new ApiItemNormalizer($decorated, $this->createStub(EntityManagerInterface::class), $this->createStub(PaperConflictsRepository::class));
        $data = $normalizer->normalize(new Statistic()->setName($name), 'jsonld');

        $this->assertSame($expectedIri, $data['@id']);
    }

    public function testGeneratedIriIsRemoved(): void
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(['@id' => '/api/.well-known/genid/0d0108f11563968581ce', '@type' => 'Solr', 'values' => []]);

        $normalizer = new ApiItemNormalizer($decorated, $this->createStub(EntityManagerInterface::class), $this->createStub(PaperConflictsRepository::class));

        $this->assertSame(['@type' => 'Solr', 'values' => []], $normalizer->normalize(new \stdClass(), 'jsonld'));
    }
}
