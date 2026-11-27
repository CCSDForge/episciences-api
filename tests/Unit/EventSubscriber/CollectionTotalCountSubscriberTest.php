<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\EventSubscriber\CollectionTotalCountSubscriber;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CollectionTotalCountSubscriberTest extends TestCase
{
    public function testPaginatorGivesTheTotal(): void
    {
        $page = new ArrayPaginator(array_fill(0, 5, new stdClass()), 0, 2);

        $this->assertSame('5', $this->dispatch(new Request(), $page)->headers->get(CollectionTotalCountSubscriber::TOTAL_COUNT_HEADER));
    }

    public function testCollectionWithoutPaginationGivesItsSize(): void
    {
        $request = new Request(attributes: ['_api_operation' => new GetCollection()]);

        $this->assertSame('3', $this->dispatch($request, [1, 2, 3])->headers->get(CollectionTotalCountSubscriber::TOTAL_COUNT_HEADER));
    }

    public function testTotalOfTheCappedCollectionIsKept(): void
    {
        $request = new Request(attributes: [
            '_api_operation' => new GetCollection(),
            UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE => 2,
            UnpaginatedCollectionLimitExtension::TOTAL_ITEMS_ATTRIBUTE => 9,
        ]);

        $this->assertSame('9', $this->dispatch($request, [1, 2])->headers->get(CollectionTotalCountSubscriber::TOTAL_COUNT_HEADER));
    }

    public function testCappedCollectionWithoutKnownTotalHasNoHeader(): void
    {
        $request = new Request(attributes: [UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE => 2]);

        $this->assertFalse($this->dispatch($request, new ArrayPaginator([1, 2], 0, 2))->headers->has(CollectionTotalCountSubscriber::TOTAL_COUNT_HEADER));
    }

    public function testItemHasNoHeader(): void
    {
        $request = new Request(attributes: ['_api_operation' => new Get()]);

        $this->assertFalse($this->dispatch($request, ['a' => 1])->headers->has(CollectionTotalCountSubscriber::TOTAL_COUNT_HEADER));
    }

    public function testErrorResponseHasNoHeader(): void
    {
        $response = $this->dispatch(new Request(), new ArrayPaginator([1], 0, 1), new Response(status: Response::HTTP_NOT_FOUND));

        $this->assertFalse($response->headers->has(CollectionTotalCountSubscriber::TOTAL_COUNT_HEADER));
    }

    public function testWriteOperationHasNoHeader(): void
    {
        $request = Request::create('/api/papers/', 'POST');

        $this->assertFalse($this->dispatch($request, new ArrayPaginator([1], 0, 1))->headers->has(CollectionTotalCountSubscriber::TOTAL_COUNT_HEADER));
    }

    private function dispatch(Request $request, mixed $result, ?Response $response = null): Response
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $response ??= new Response();
        $subscriber = new CollectionTotalCountSubscriber();

        $subscriber->setTotalCount(new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $result));
        $subscriber->addTotalCountHeader(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }
}
