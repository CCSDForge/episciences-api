<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\StatResourceSubscriber;
use App\Exception\ResourceNotFoundException;
use App\Resource\AbstractStatResource;
use App\Resource\SubmissionOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class StatResourceSubscriberTest extends TestCase
{
    private StatResourceSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new StatResourceSubscriber(
            $this->createStub(\Doctrine\ORM\EntityManagerInterface::class)
        );
    }

    // ── getSubscribedEvents ──────────────────────────────────────────────────

    public function testGetSubscribedEventsRegistersViewEvent(): void
    {
        $events = StatResourceSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::VIEW, $events);
        $this->assertSame('checkStatResourceAvailability', $events[KernelEvents::VIEW][0]);
    }

    // ── helper ───────────────────────────────────────────────────────────────

    private function makeEvent(string $method, mixed $controllerResult): ViewEvent
    {
        $request = Request::create('/api/test', $method);

        return new ViewEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $controllerResult);
    }

    private function makeStatResource(array $availableFilters, array $requestedFilters, mixed $value): AbstractStatResource
    {
        $resource = new SubmissionOutput();
        $resource->setAvailableFilters($availableFilters);
        $resource->setRequestedFilters($requestedFilters);
        $resource->setValue($value);
        return $resource;
    }

    // ── non-safe method → early return ────────────────────────────────────────

    public function testNonSafeMethodReturnsEarly(): void
    {
        // POST is not safe — subscriber must not throw even with null value
        $resource = $this->makeStatResource(['rvid'], [], null);
        $event = $this->makeEvent('POST', $resource);

        // no exception expected
        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true); // reached here without exception
    }

    public function testPutMethodReturnsEarly(): void
    {
        $resource = $this->makeStatResource([], [], null);
        $event = $this->makeEvent('PUT', $resource);

        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true);
    }

    // ── non-AbstractStatResource result → early return ────────────────────────

    public function testNonStatResourceControllerResultIsIgnored(): void
    {
        $event = $this->makeEvent('GET', new \stdClass());

        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true);
    }

    public function testNullControllerResultIsIgnored(): void
    {
        $event = $this->makeEvent('GET', null);

        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true);
    }

    public function testArrayControllerResultIsIgnored(): void
    {
        $event = $this->makeEvent('GET', ['data' => 'value']);

        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true);
    }

    // ── filters equality → early return ──────────────────────────────────────

    public function testExactFilterMatchDoesNotThrow(): void
    {
        // available = requested keys → equality → return early (even with null value)
        $resource = $this->makeStatResource(['rvid'], ['rvid' => 5], null);
        $event = $this->makeEvent('GET', $resource);

        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true);
    }

    public function testEmptyFiltersOnBothSidesDoesNotThrow(): void
    {
        $resource = $this->makeStatResource([], [], 42);
        $event = $this->makeEvent('GET', $resource);

        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true);
    }

    // ── unknown requested filter → ResourceNotFoundException ─────────────────

    public function testUnknownRequestedFilterThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        // 'unknown' is not in available filters
        $resource = $this->makeStatResource(
            ['rvid'],
            ['rvid' => 5, 'unknown' => 'bad'],
            42
        );
        $event = $this->makeEvent('GET', $resource);

        $this->subscriber->checkStatResourceAvailability($event);
    }

    public function testExceptionMessageContainsUnknownParameterName(): void
    {
        try {
            $resource = $this->makeStatResource(
                ['rvid', 'year'],
                ['rvid' => 1, 'badParam' => 'x'],
                10
            );
            $event = $this->makeEvent('GET', $resource);
            $this->subscriber->checkStatResourceAvailability($event);
            $this->fail('Expected ResourceNotFoundException');
        } catch (ResourceNotFoundException $e) {
            $this->assertStringContainsString('badParam', $e->getMessage());
        }
    }

    public function testExceptionMessageContainsAvailableFilters(): void
    {
        try {
            $resource = $this->makeStatResource(
                ['rvid', 'year'],
                ['rvid' => 1, 'notAvailable' => 'x'],
                5
            );
            $event = $this->makeEvent('GET', $resource);
            $this->subscriber->checkStatResourceAvailability($event);
            $this->fail('Expected ResourceNotFoundException');
        } catch (ResourceNotFoundException $e) {
            $this->assertStringContainsString('rvid', $e->getMessage());
            $this->assertStringContainsString('year', $e->getMessage());
        }
    }

    // ── null value with valid filters → ResourceNotFoundException ─────────────

    public function testNullValueWithKnownSubsetOfFiltersThrows(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        // requested filters are a strict subset of available → no unknown params
        // but arrayDiff.out = [] → falls through to value === null check
        $resource = $this->makeStatResource(
            ['rvid', 'year', 'details'],
            ['rvid' => 7],
            null
        );
        $event = $this->makeEvent('GET', $resource);

        $this->subscriber->checkStatResourceAvailability($event);
    }

    public function testNullValueWithEmptyRequestedFiltersThrows(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        // available has items, none requested — subset, no unknown params, value null
        $resource = $this->makeStatResource(['rvid', 'year'], [], null);
        $event = $this->makeEvent('GET', $resource);

        $this->subscriber->checkStatResourceAvailability($event);
    }

    // ── non-null value with valid filters → no exception ─────────────────────

    public function testNonNullValueWithValidSubsetOfFiltersDoesNotThrow(): void
    {
        // available = ['rvid', 'year'], requested = ['rvid' => 5] → subset → check value
        $resource = $this->makeStatResource(['rvid', 'year'], ['rvid' => 5], 42);
        $event = $this->makeEvent('GET', $resource);

        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true);
    }

    public function testZeroValueIsNotConsideredNull(): void
    {
        $resource = $this->makeStatResource(['rvid'], ['rvid' => 5], 0);
        $event = $this->makeEvent('GET', $resource);

        // 0 !== null → no exception
        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true);
    }

    public function testArrayValueIsNotConsideredNull(): void
    {
        $resource = $this->makeStatResource(['rvid'], ['rvid' => 5], ['count' => 3]);
        $event = $this->makeEvent('GET', $resource);

        $this->subscriber->checkStatResourceAvailability($event);
        $this->assertTrue(true);
    }

    // ── HEAD request is safe ──────────────────────────────────────────────────

    public function testHeadRequestBehavesSameAsGet(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $resource = $this->makeStatResource(['rvid', 'year'], [], null);
        $event = $this->makeEvent('HEAD', $resource);

        $this->subscriber->checkStatResourceAvailability($event);
    }
}
