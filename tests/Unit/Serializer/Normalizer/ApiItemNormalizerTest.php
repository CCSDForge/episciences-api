<?php

namespace App\Tests\Unit\Serializer\Normalizer;

use App\Entity\Section;
use App\Repository\PaperConflictsRepository;
use App\Resource\Statistic;
use App\Repository\SectionRepository;
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

    public function testTotalPublishedArticlesIsCountedFromThePapers(): void
    {
        $this->assertSame(0, $this->normalizeSection(new Section()->setRvid(3)->setSid(9))['totalPublishedArticles']);
    }

    /**
     * The sections assigned to the board members are built without their papers, with the number
     * of published articles counted by the query: it must not be recounted from the empty collection.
     */
    public function testTotalPublishedArticlesAlreadySetIsKept(): void
    {
        $section = new Section()->setRvid(3)->setSid(9);
        $section->setTotalPublishedArticles(12);

        $this->assertSame(12, $this->normalizeSection($section)['totalPublishedArticles']);
    }

    private function normalizeSection(Section $section): array
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(['sid' => 9]);

        $sectionRepository = $this->createStub(SectionRepository::class);
        $sectionRepository->method('getCommittee')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($sectionRepository);

        $normalizer = new ApiItemNormalizer($decorated, $entityManager, $this->createStub(PaperConflictsRepository::class));

        return $normalizer->normalize($section, 'jsonld', []);
    }
}
