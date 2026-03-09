<?php

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\StatResourceSubscriber;
use App\Exception\ResourceNotFoundException;
use App\Resource\AbstractStatResource;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class StatResourceSubscriberTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private StatResourceSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = new StatResourceSubscriber($this->em);
    }

    // ------------------------------------------------------------------ helpers

    private function makeEvent(mixed $result, string $method = 'GET'): ViewEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/stats', $method);
        $event   = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $result);
        return $event;
    }

    private function makeStatResource(array $available, array $requested, mixed $value): AbstractStatResource
    {
        $resource = new class extends AbstractStatResource {};
        $resource->setAvailableFilters($available);
        $resource->setRequestedFilters($requested);
        $resource->setValue($value);
        return $resource;
    }

    // ------------------------------------------------------------------ metadata

    public function testGetSubscribedEventsListensOnViewEvent(): void
    {
        $events = StatResourceSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::VIEW, $events);
        $this->assertSame('checkStatResourceAvailability', $events[KernelEvents::VIEW][0]);
    }

    // ------------------------------------------------------------------ non-safe method → early return

    public function testPostRequestIsIgnored(): void
    {
        // AbstractStatResource result — but POST → subscriber returns early
        $resource = $this->makeStatResource([], [], null);
        $event    = $this->makeEvent($resource, 'POST');

        // no exception expected
        $this->subscriber->checkStatResourceAvailability($event);
        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------ non-StatResource result → early return

    public function testNonStatResourceResultIsIgnored(): void
    {
        $event = $this->makeEvent(new \stdClass());

        $this->subscriber->checkStatResourceAvailability($event);
        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------ exact match of filters → no exception

    public function testExactFilterMatchDoesNotThrow(): void
    {
        $resource = $this->makeStatResource(
            ['year', 'rvid'],
            ['year' => 2024, 'rvid' => 1],
            42
        );
        $event = $this->makeEvent($resource);

        $this->subscriber->checkStatResourceAvailability($event);
        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------ unknown filter parameter → ResourceNotFoundException

    public function testUnknownFilterParameterThrowsResourceNotFoundException(): void
    {
        $resource = $this->makeStatResource(
            ['year'],
            ['year' => 2024, 'unknown_param' => 'foo'],
            42
        );
        $event = $this->makeEvent($resource);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('unknown_param');

        $this->subscriber->checkStatResourceAvailability($event);
    }

    // ------------------------------------------------------------------ null value with valid subset filters → ResourceNotFoundException

    public function testNullValueWithValidFiltersThrowsResourceNotFoundException(): void
    {
        $resource = $this->makeStatResource(
            ['year', 'rvid'],
            ['year' => 2024],  // subset — valid, but value is null
            null
        );
        $event = $this->makeEvent($resource);

        $this->expectException(ResourceNotFoundException::class);

        $this->subscriber->checkStatResourceAvailability($event);
    }

    // ------------------------------------------------------------------ no requested filters, value set → no exception

    public function testNoRequestedFiltersWithValueDoesNotThrow(): void
    {
        $resource = $this->makeStatResource(['year', 'rvid'], [], 100);
        $event    = $this->makeEvent($resource);

        $this->subscriber->checkStatResourceAvailability($event);
        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------ no requested filters, null value → ResourceNotFoundException

    public function testNoRequestedFiltersWithNullValueThrows(): void
    {
        $resource = $this->makeStatResource(['year'], [], null);
        $event    = $this->makeEvent($resource);

        $this->expectException(ResourceNotFoundException::class);

        $this->subscriber->checkStatResourceAvailability($event);
    }
}