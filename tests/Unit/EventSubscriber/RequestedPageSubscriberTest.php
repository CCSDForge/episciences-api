<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\Pagination;
use App\EventSubscriber\RequestedPageSubscriber;
use App\Exception\ResourceNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Only an existing page of a paginated collection may be requested.
 */
class RequestedPageSubscriberTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function invalidPageProvider(): array
    {
        return [
            'zero' => ['page=0'],
            'negative' => ['page=-1'],
            'decimal' => ['page=1.5'],
            'trailing characters' => ['page=2abc'],
            'not a number' => ['page=abc'],
            'empty' => ['page='],
            'leading zero' => ['page=01'],
            'plus sign' => ['page=%2B1'],
            'spaces' => ['page=%201'],
        ];
    }

    #[DataProvider('invalidPageProvider')]
    public function testRejectsAPageThatIsNotAPositiveInteger(string $query): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->checkPageFormat($query, new GetCollection());
    }

    /**
     * @return array<string, array{string, Operation|null}>
     */
    public static function acceptedPageProvider(): array
    {
        return [
            'no page' => ['', new GetCollection()],
            'first page' => ['page=1', new GetCollection()],
            'any page' => ['page=12', new GetCollection()],
            'page as a list, left to the parameter validation' => ['page[]=abc', new GetCollection()],
            'without pagination' => ['pagination=false&page=abc', new GetCollection()],
            'pagination disabled on the operation' => ['page=abc', new GetCollection(paginationEnabled: false)],
            'item operation' => ['page=abc', new Get()],
            'no operation' => ['page=abc', null],
        ];
    }

    #[DataProvider('acceptedPageProvider')]
    public function testAcceptsAValidOrUnusedPage(string $query, ?Operation $operation): void
    {
        $this->checkPageFormat($query, $operation);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function existingPageProvider(): array
    {
        return [
            'first page, empty' => ['page=1', []],
            'no page, empty' => ['', new ArrayPaginator([], 0, 2)],
            'last page' => ['page=2&itemsPerPage=2', new ArrayPaginator([1, 2, 3], 2, 2)],
            'without pagination' => ['pagination=false&page=3', []],
            'not a collection' => ['page=3', null],
        ];
    }

    #[DataProvider('existingPageProvider')]
    public function testServesAnExistingPage(string $query, mixed $result): void
    {
        $this->checkPageExists($query, $result);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyResultProvider(): array
    {
        return [
            'paginator' => [new ArrayPaginator([1, 2, 3], 4, 2)],
            'array' => [[]],
        ];
    }

    #[DataProvider('emptyResultProvider')]
    public function testRejectsAPageAfterTheLastOne(mixed $result): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->checkPageExists('page=3&itemsPerPage=2', $result);
    }

    /**
     * @return array<string, array{int, int, int}> page, items per page, offset
     */
    public static function offsetProvider(): array
    {
        return [
            'first page' => [1, 30, 0],
            'second page' => [2, 30, 30],
            'largest page of one item' => [PHP_INT_MAX, 1, PHP_INT_MAX - 1],
            'second page of the largest size' => [2, PHP_INT_MAX, PHP_INT_MAX],
        ];
    }

    #[DataProvider('offsetProvider')]
    public function testFirstResultIsTheOffsetOfThePage(int $page, int $itemsPerPage, int $expected): void
    {
        $this->assertSame($expected, RequestedPageSubscriber::firstResult($page, $itemsPerPage));
    }

    /**
     * @return array<string, array{int, int}> page, items per page
     */
    public static function tooLargePageProvider(): array
    {
        return [
            'largest page' => [PHP_INT_MAX, 30],
            'third page of the largest size' => [3, PHP_INT_MAX],
        ];
    }

    /**
     * An offset beyond the integers is a bad request (400), as API Platform for the Doctrine collections,
     * not a float given to ArrayPaginator (500).
     */
    #[DataProvider('tooLargePageProvider')]
    public function testAPageWhoseOffsetIsNotAnIntegerIsABadRequest(int $page, int $itemsPerPage): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Page parameter is too large.');

        RequestedPageSubscriber::firstResult($page, $itemsPerPage);
    }

    private function checkPageFormat(string $query, ?Operation $operation): void
    {
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $this->request($query, $operation), HttpKernelInterface::MAIN_REQUEST);

        new RequestedPageSubscriber(new Pagination(['client_enabled' => true, 'client_items_per_page' => true]))->checkPageFormat($event);
    }

    private function checkPageExists(string $query, mixed $result): void
    {
        $event = new ViewEvent($this->createStub(HttpKernelInterface::class), $this->request($query, new GetCollection()), HttpKernelInterface::MAIN_REQUEST, $result);

        new RequestedPageSubscriber(new Pagination(['client_enabled' => true, 'client_items_per_page' => true]))->checkPageExists($event);
    }

    private function request(string $query, ?Operation $operation): Request
    {
        $request = Request::create('/api/papers/?' . $query);

        if ($operation !== null) {
            $request->attributes->set('_api_operation', $operation);
        }

        return $request;
    }
}
