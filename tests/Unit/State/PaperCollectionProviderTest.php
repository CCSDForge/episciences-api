<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Paper;
use App\State\PaperCollectionProvider;
use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class PaperCollectionProviderTest extends TestCase
{
    private GetCollection $operation;

    protected function setUp(): void
    {
        $this->operation = new GetCollection(class: Paper::class, paginationMaximumItemsPerPage: 2);
    }

    public function testResultAboveTheMaximumIsTruncatedAndFlagged(): void
    {
        $request = new Request();

        $papers = $this->createProvider(['a', 'b', 'c'])->provide($this->operation, [], ['request' => $request]);

        $this->assertSame(['a', 'b'], $papers);
        $this->assertSame(2, $request->attributes->get(PaperCollectionProvider::TRUNCATED_ATTRIBUTE));
    }

    public function testResultWithinTheMaximumIsUnchanged(): void
    {
        $request = new Request();

        $papers = $this->createProvider(['a', 'b'])->provide($this->operation, [], ['request' => $request]);

        $this->assertSame(['a', 'b'], $papers);
        $this->assertFalse($request->attributes->has(PaperCollectionProvider::TRUNCATED_ATTRIBUTE));
    }

    public function testPaginatedResultIsUnchanged(): void
    {
        $paginator = new ArrayIterator(['a', 'b', 'c']);

        $this->assertSame($paginator, $this->createProvider($paginator)->provide($this->operation, [], ['request' => new Request()]));
    }

    public function testResultIsTruncatedWithoutRequest(): void
    {
        $this->assertSame(['a', 'b'], $this->createProvider(['a', 'b', 'c'])->provide($this->operation));
    }

    /**
     * @param object|string[] $result
     */
    private function createProvider(object|array $result): PaperCollectionProvider
    {
        $collectionProvider = $this->createStub(ProviderInterface::class);
        $collectionProvider->method('provide')->willReturn($result);

        return new PaperCollectionProvider($collectionProvider);
    }
}
