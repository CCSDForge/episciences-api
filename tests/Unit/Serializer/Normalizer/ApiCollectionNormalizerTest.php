<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Entity\Paper;
use App\Entity\Review;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Serializer\Normalizer\ApiCollectionNormalizer;
use App\Service\Solarium\Client;
use App\Service\Solr\SolrAuthorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiCollectionNormalizerTest extends TestCase
{
    private const array NESTED_DATA = [['name' => 'a'], ['name' => 'b']];

    public function testSupportsTheSameTypesAsTheHydraCollectionNormalizer(): void
    {
        $supportedTypes = ['native-array' => true, '\Traversable' => true];

        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('getSupportedTypes')->willReturn($supportedTypes);

        $this->assertSame($supportedTypes, $this->createNormalizer($decorated)->getSupportedTypes(ApiCollectionNormalizer::FORMAT));
    }

    public function testSupportsNormalizationPassesTheContext(): void
    {
        $context = ['resource_class' => Paper::class];

        $decorated = $this->createMock(NormalizerInterface::class);
        $decorated->expects($this->once())->method('supportsNormalization')->with([], ApiCollectionNormalizer::FORMAT, $context)->willReturn(true);

        $this->assertTrue($this->createNormalizer($decorated)->supportsNormalization([], ApiCollectionNormalizer::FORMAT, $context));
    }

    public function testArrayNestedInAResourceIsReturnedAsIs(): void
    {
        $context = [
            'resource_class' => Paper::class,
            'api_sub_level' => true,
            'operation' => new GetCollection(class: Paper::class),
            'uri' => '/api/papers/?pagination=false&rvcode=epijinfo',
        ];

        $this->assertSame(self::NESTED_DATA, $this->normalizeNested($context));
    }

    public function testArrayWithoutResourceClassIsReturnedAsIs(): void
    {
        $this->assertSame(self::NESTED_DATA, $this->normalizeNested(['operation' => new GetCollection(class: Paper::class)]));
    }

    public function testTruncatedCollectionGetsTheTotalNumberOfItems(): void
    {
        $data = $this->normalizeCollection([
            UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE => 2,
            UnpaginatedCollectionLimitExtension::TOTAL_ITEMS_ATTRIBUTE => 9,
        ]);

        $this->assertSame(9, $data['hydra:totalItems']);
        $this->assertArrayNotHasKey('hydra:view', $data);
    }

    public function testCompleteCollectionKeepsItsCount(): void
    {
        // the total of a complete collection is also set (X-Total-Count), and equals the count
        $data = $this->normalizeCollection([UnpaginatedCollectionLimitExtension::TOTAL_ITEMS_ATTRIBUTE => 9]);

        $this->assertSame(2, $data['hydra:totalItems']);
    }

    public function testTruncatedCollectionWithoutKnownTotalKeepsItsCount(): void
    {
        $data = $this->normalizeCollection([UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE => 2]);

        $this->assertSame(2, $data['hydra:totalItems']);
    }

    /**
     * Hydra collection of 2 members (as counted by the decorated normalizer), for the given request attributes.
     *
     * @param array<string, mixed> $attributes
     * @return array<mixed>
     */
    private function normalizeCollection(array $attributes): array
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(['@id' => '/api/journals/', 'hydra:totalItems' => 2, 'hydra:member' => []]);

        $requestStack = new RequestStack();
        $requestStack->push(new Request(attributes: $attributes));

        $normalizer = $this->createNormalizer($decorated);
        $normalizer->setRequestStack($requestStack);

        $data = $normalizer->normalize([], ApiCollectionNormalizer::FORMAT, [
            'resource_class' => Review::class,
            'operation' => new GetCollection(class: Review::class),
            'uri' => '/api/journals/?pagination=false',
        ]);
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * The facets of the papers are computed on the requested section; the lists ignore the requested years and
     * types, the counts of each facet follow the filter of the other one
     */
    public function testThePaperFacetsAreComputedOnTheRequestedSectionAndVolume(): void
    {
        $scope = ['rvid' => 1, 'sid' => '66', 'vid' => null];

        $range = $this->normalizePapers('/api/papers/?rvcode=dev&sid=66&year[]=2015&type[]=article', $scope, requestedYears: ['2015'], requestedTypes: ['article']);

        $this->assertSame([
            'publicationYears' => [2015, 2014],
            'types' => ['article', 'conferenceobject'],
            'counts' => [
                'publicationYears' => [2015 => 2, 2014 => 0],
                'types' => ['article' => 2, 'conferenceobject' => 83],
            ],
        ], $range);
    }

    public function testThePaperFacetsOfAVolumeAreComputedOnTheVolume(): void
    {
        $this->normalizePapers('/api/papers/?rvcode=dev&vid[]=7&vid[]=8', ['rvid' => 1, 'sid' => null, 'vid' => ['7', '8']]);
    }

    /**
     * No paper matches the filters (e.g. type[]=dataset in a section without dataset): the facets stay those of the scope
     */
    public function testThePaperFacetsKeepTheScopeWhenNoPaperMatches(): void
    {
        $scope = ['rvid' => 1, 'sid' => '66', 'vid' => null];

        $this->normalizePapers('/api/papers/?rvcode=dev&sid=66&type[]=dataset', $scope, members: [], requestedTypes: ['dataset']);
    }

    /**
     * The journal given by its identifier only (rvid, without rvcode) restricts the facets to the journal
     */
    public function testThePaperFacetsUseTheRvidWithoutJournalCode(): void
    {
        $this->normalizePapers('/api/papers/?rvid=1&sid=66', ['rvid' => '1', 'sid' => '66', 'vid' => null], withJournal: false);
    }

    public function testThePaperFacetsOfTheWholeJournalAreUnchanged(): void
    {
        $range = $this->normalizePapers('/api/papers/?rvcode=dev', ['rvid' => 1, 'sid' => null, 'vid' => null]);

        $this->assertSame([2015, 2014], $range['publicationYears']);
        $this->assertSame(['article', 'conferenceobject'], $range['types']);
    }

    /**
     * Only the types of the accepted papers, counted whatever the requested years (ignored by their list),
     * also when no paper matches the other filters
     */
    public function testTheAcceptedPaperFacetsKeepTheScopeWhenNoPaperMatches(): void
    {
        $scope = ['rvid' => 1, 'sid' => '66', 'vid' => null];

        $range = $this->normalizePapers('/api/papers/?rvcode=dev&sid=66&only_accepted=true&year[]=2015&type[]=dataset', $scope, isOnlyAccepted: true, members: []);

        $this->assertSame([
            'types' => ['article', 'conferenceobject'],
            'counts' => ['types' => ['article' => 2, 'conferenceobject' => 83]],
        ], $range);
    }

    /**
     * A scope without any paper: the counts are JSON objects, not empty lists
     */
    public function testTheCountsOfAnEmptyScopeAreObjects(): void
    {
        $range = $this->normalizePapers('/api/papers/?rvcode=dev&sid=99', ['rvid' => 1, 'sid' => '99', 'vid' => null], members: [], years: [], types: []);

        $this->assertSame('{"publicationYears":{},"types":{}}', json_encode($range['counts'], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $scope the expected scope of the facets
     * @param list<array<string, mixed>> $members
     * @param list<string> $requestedYears the expected years of the counts by type
     * @param list<string> $requestedTypes the expected types of the counts by year
     * @param list<int> $years
     * @param list<string> $types
     *
     * @return mixed the hydra:range of the response
     */
    private function normalizePapers(
        string $uri,
        array $scope,
        bool $isOnlyAccepted = false,
        array $members = [['docid' => 1]],
        bool $withJournal = true,
        array $requestedYears = [],
        array $requestedTypes = [],
        array $years = [2015, 2014],
        array $types = ['article', 'conferenceobject'],
    ): mixed {
        $journal = null;

        if ($withJournal) {
            $journal = $this->createStub(Review::class);
            $journal->method('getRvid')->willReturn(1);
        }

        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(['@id' => '/api/papers/', 'hydra:totalItems' => count($members), 'hydra:member' => $members]);

        $typesScope = $scope + ['isOnlyAccepted' => $isOnlyAccepted];
        $papersRepository = $this->createMock(PapersRepository::class);
        $papersRepository->expects($this->once())->method('getTypes')->with($typesScope)->willReturn($types);
        // dataset is not in the list of the scope: not in the counts
        $papersRepository->expects($this->once())->method('countByType')->with($typesScope, $requestedYears)
            ->willReturn($types === [] ? [] : ['article' => 2, 'conferenceobject' => 83, 'dataset' => 1]);

        if ($isOnlyAccepted) {
            $papersRepository->expects($this->never())->method('getRange');
            $papersRepository->expects($this->never())->method('countByPublicationYear');
        } else {
            $papersRepository->expects($this->once())->method('getRange')->with($scope)->willReturn($years);
            $papersRepository->expects($this->once())->method('countByPublicationYear')->with($scope, $requestedTypes)
                ->willReturn($years === [] ? [] : [2015 => 2]);
        }

        $journalRepository = $this->createStub(ReviewRepository::class);
        $journalRepository->method('getJournalByIdentifier')->willReturn($journal);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [Review::class, $journalRepository],
            [Paper::class, $papersRepository],
        ]);

        $context = [
            'resource_class' => Paper::class,
            'operation' => new GetCollection(class: Paper::class),
            'uri' => $uri,
        ];

        $data = $this->createNormalizer($decorated, $entityManager)->normalize([], ApiCollectionNormalizer::FORMAT, $context);

        return $data['hydra:range'] ?? null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function normalizeNested(array $context): mixed
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(self::NESTED_DATA);

        // A nested array is not a collection: no journal lookup nor hydra context
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');

        return $this->createNormalizer($decorated, $entityManager)->normalize(self::NESTED_DATA, ApiCollectionNormalizer::FORMAT, $context);
    }

    private function createNormalizer(NormalizerInterface $decorated, ?EntityManagerInterface $entityManager = null): ApiCollectionNormalizer
    {
        return new ApiCollectionNormalizer(
            $decorated,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(SolrAuthorService::class),
            $this->createStub(Client::class),
            null,
            $this->createStub(Security::class)
        );
    }
}
