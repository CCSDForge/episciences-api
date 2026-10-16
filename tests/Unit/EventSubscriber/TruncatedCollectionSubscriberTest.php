<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\EventSubscriber\TruncatedCollectionSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class TruncatedCollectionSubscriberTest extends TestCase
{
    public function testTruncatedCollectionAddsTheHeader(): void
    {
        $request = new Request();
        $request->attributes->set(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE, 1000);

        $response = $this->dispatch($request);

        $this->assertSame('1000', $response->headers->get(TruncatedCollectionSubscriber::TRUNCATED_HEADER));
    }

    public function testCompleteCollectionHasNoHeader(): void
    {
        $response = $this->dispatch(new Request());

        $this->assertFalse($response->headers->has(TruncatedCollectionSubscriber::TRUNCATED_HEADER));
    }

    private function dispatch(Request $request): Response
    {
        $response = new Response();
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        new TruncatedCollectionSubscriber()->addTruncatedHeader($event);

        return $response;
    }
}
