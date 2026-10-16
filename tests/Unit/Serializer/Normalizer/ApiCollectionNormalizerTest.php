<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use ApiPlatform\Metadata\GetCollection;
use App\Entity\Paper;
use App\Repository\PapersRepository;
use App\Serializer\Normalizer\ApiCollectionNormalizer;
use App\Service\Solarium\Client;
use App\Service\Solr\SolrAuthorService;
use App\State\PaperCollectionProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiCollectionNormalizerTest extends TestCase
{
    private const array PAPERS_DATA = [
        'hydra:member' => [['docid' => 1], ['docid' => 2]],
        'hydra:totalItems' => 2,
    ];

    public function testTruncatedPaperCollectionIsFlagged(): void
    {
        $request = new Request();
        $request->attributes->set(PaperCollectionProvider::TRUNCATED_ATTRIBUTE, 2);

        $data = $this->normalize($request);

        $this->assertTrue($data['hydra:truncated']);
        $this->assertSame(2, $data['hydra:maxItems']);
        $this->assertSame(self::PAPERS_DATA['hydra:member'], $data['hydra:member']);
    }

    public function testCompletePaperCollectionIsNotFlagged(): void
    {
        $data = $this->normalize(new Request());

        $this->assertArrayNotHasKey('hydra:truncated', $data);
        $this->assertArrayNotHasKey('hydra:maxItems', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(Request $request): array
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(self::PAPERS_DATA);

        $papersRepository = $this->createStub(PapersRepository::class);
        $papersRepository->method('getTypes')->willReturn([]);
        $papersRepository->method('getRange')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($papersRepository);

        $normalizer = new ApiCollectionNormalizer(
            $decorated,
            $entityManager,
            $this->createStub(SolrAuthorService::class),
            $this->createStub(Client::class),
            null,
            $this->createStub(Security::class)
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $normalizer->setRequestStack($requestStack);

        return $normalizer->normalize([], ApiCollectionNormalizer::FORMAT, [
            'operation' => new GetCollection(class: Paper::class),
            'uri' => '/api/papers/?pagination=false',
        ]);
    }
}
