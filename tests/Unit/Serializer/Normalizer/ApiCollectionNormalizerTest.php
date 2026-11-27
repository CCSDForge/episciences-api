<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use ApiPlatform\Metadata\GetCollection;
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

    /**
     * The facets of the papers are computed on the requested section or volume, without the requested years and types
     */
    public function testThePaperFacetsAreComputedOnTheRequestedSectionAndVolume(): void
    {
        $scope = ['rvid' => 1, 'sid' => '66', 'vid' => null];

        $range = $this->normalizePapers('/api/papers/?rvcode=dev&sid=66&year[]=2015&type[]=article', $scope, $scope);

        $this->assertSame(['publicationYears' => [2015, 2014], 'types' => ['article', 'conferenceobject']], $range);
    }

    public function testThePaperFacetsOfAVolumeAreComputedOnTheVolume(): void
    {
        $scope = ['rvid' => 1, 'sid' => null, 'vid' => ['7', '8']];

        $this->normalizePapers('/api/papers/?rvcode=dev&vid[]=7&vid[]=8', $scope, $scope);
    }

    /**
     * No paper matches the filters (e.g. type[]=dataset in a section without dataset): the facets stay those of the scope
     */
    public function testThePaperFacetsKeepTheScopeWhenNoPaperMatches(): void
    {
        $scope = ['rvid' => 1, 'sid' => '66', 'vid' => null];

        $this->normalizePapers('/api/papers/?rvcode=dev&sid=66&type[]=dataset', $scope, $scope, []);
    }

    /**
     * The journal given by its identifier only (rvid, without rvcode) restricts the facets to the journal
     */
    public function testThePaperFacetsUseTheRvidWithoutJournalCode(): void
    {
        $scope = ['rvid' => '1', 'sid' => '66', 'vid' => null];

        $this->normalizePapers('/api/papers/?rvid=1&sid=66', $scope, $scope, withJournal: false);
    }

    public function testThePaperFacetsOfTheWholeJournalAreUnchanged(): void
    {
        $scope = ['rvid' => 1, 'sid' => null, 'vid' => null];

        $this->normalizePapers('/api/papers/?rvcode=dev', $scope, $scope);
    }

    /**
     * Only the types of the accepted papers, also when no paper matches the other filters
     */
    public function testTheAcceptedPaperFacetsKeepTheScopeWhenNoPaperMatches(): void
    {
        $scope = ['rvid' => 1, 'sid' => '66', 'vid' => null];

        $range = $this->normalizePapers('/api/papers/?rvcode=dev&sid=66&only_accepted=true&type[]=dataset', $scope + ['isOnlyAccepted' => true], null, []);

        $this->assertSame(['types' => ['article', 'conferenceobject']], $range);
    }

    /**
     * @param array<string, mixed> $expectedTypesFilters
     * @param array<string, mixed>|null $expectedRangeFilters null when the years are not computed
     * @param list<array<string, mixed>> $members
     *
     * @return mixed the hydra:range of the response
     */
    private function normalizePapers(
        string $uri,
        array $expectedTypesFilters,
        ?array $expectedRangeFilters,
        array $members = [['docid' => 1]],
        bool $withJournal = true,
    ): mixed {
        $journal = null;

        if ($withJournal) {
            $journal = $this->createStub(Review::class);
            $journal->method('getRvid')->willReturn(1);
        }

        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(['@id' => '/api/papers/', 'hydra:totalItems' => count($members), 'hydra:member' => $members]);

        $papersRepository = $this->createMock(PapersRepository::class);
        $papersRepository->expects($this->once())->method('getTypes')
            ->with($expectedTypesFilters + ['isOnlyAccepted' => false])
            ->willReturn(['article', 'conferenceobject']);

        if ($expectedRangeFilters === null) {
            $papersRepository->expects($this->never())->method('getRange');
        } else {
            $papersRepository->expects($this->once())->method('getRange')->with($expectedRangeFilters)->willReturn([2015, 2014]);
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
