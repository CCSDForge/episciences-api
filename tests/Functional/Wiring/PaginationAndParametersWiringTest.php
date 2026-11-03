<?php

declare(strict_types=1);

namespace App\Tests\Functional\Wiring;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Symfony\EventListener\ReadListener;
use ApiPlatform\Symfony\EventListener\WriteListener;
use App\Entity\Volume;
use App\EventSubscriber\RequestedPageSubscriber;
use App\Metadata\SingleValueQueryParametersResourceMetadataCollectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\EventListener\FirewallListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The query parameters and the page are checked by services whose order is set in the configuration
 * (decoration priority of the metadata factory, priorities of the listeners): the unit tests call them directly,
 * this test checks that they are wired in the kernel, so that an upgrade changing an order does not go unnoticed.
 *
 * Needs neither a database nor Solr: the requests are rejected before the collection is read.
 */
class PaginationAndParametersWiringTest extends KernelTestCase
{
    /**
     * The metadata factory decorates the one of API Platform inside its parameter factories (decoration_priority: 1001):
     * the single-value parameters are on the operations resolved by the container.
     */
    public function testTheSingleValueParametersAreOnTheResolvedOperations(): void
    {
        self::bootKernel();

        /** @var ResourceMetadataCollectionFactoryInterface $factory */
        $factory = static::getContainer()->get('api_platform.metadata.resource.metadata_collection_factory');
        $collection = null;

        foreach ($factory->create(Volume::class) as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                if ($operation instanceof GetCollection) {
                    $collection = $operation;
                }
            }
        }

        $this->assertNotNull($collection);
        $rvcode = $collection->getParameters()?->get('rvcode', QueryParameter::class);
        $this->assertInstanceOf(QueryParameter::class, $rvcode);
        $this->assertTrue($rvcode->getExtraProperties()[SingleValueQueryParametersResourceMetadataCollectionFactory::SINGLE_VALUE] ?? false);
    }

    /**
     * @return array<string, array{string, int}> query of GET /api/volumes, expected status
     */
    public static function rejectedRequestProvider(): array
    {
        return [
            'single-value parameter as a list' => ['rvcode[]=dev', Response::HTTP_UNPROCESSABLE_ENTITY],
            'page that is not a positive integer' => ['page=abc', Response::HTTP_BAD_REQUEST],
            'page zero' => ['page=0', Response::HTTP_BAD_REQUEST],
        ];
    }

    /**
     * Through the whole HTTP kernel: the parameter (422) and the page (400) are rejected before the collection is read.
     */
    #[DataProvider('rejectedRequestProvider')]
    public function testAnInvalidRequestIsRejectedBeforeTheCollectionIsRead(string $query, int $expectedStatus): void
    {
        self::bootKernel();

        $request = Request::create('/api/volumes?' . $query, server: ['HTTP_ACCEPT' => 'application/ld+json', 'HTTPS' => 'on']);
        $response = self::$kernel->handle($request);

        $this->assertSame($expectedStatus, $response->getStatusCode(), (string)$response->getContent());
    }

    /**
     * The page format is checked after the firewall and before the collection is read;
     * the last page is checked before the result is written (a page after the last one: 404, see the unit tests).
     */
    public function testThePageIsCheckedBetweenTheFirewallAndTheReadAndBeforeTheWrite(): void
    {
        self::bootKernel();

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');

        $checkPageFormat = $this->priority($dispatcher, KernelEvents::REQUEST, RequestedPageSubscriber::class, 'checkPageFormat');
        $this->assertGreaterThan($checkPageFormat, $this->priority($dispatcher, KernelEvents::REQUEST, FirewallListener::class, 'onKernelRequest'));
        $this->assertGreaterThan($this->priority($dispatcher, KernelEvents::REQUEST, ReadListener::class), $checkPageFormat);

        $checkPageExists = $this->priority($dispatcher, KernelEvents::VIEW, RequestedPageSubscriber::class, 'checkPageExists');
        $this->assertGreaterThan($this->priority($dispatcher, KernelEvents::VIEW, WriteListener::class), $checkPageExists);
    }

    /**
     * Priority of the listener of an event that is an instance of the class (e.g. the traceable firewall in debug mode)
     */
    private function priority(EventDispatcherInterface $dispatcher, string $eventName, string $class, ?string $method = null): int
    {
        foreach ($dispatcher->getListeners($eventName) as $listener) {
            if (is_array($listener) && $listener[0] instanceof $class && ($method === null || $listener[1] === $method)) {
                return (int)$dispatcher->getListenerPriority($eventName, $listener);
            }
        }

        $this->fail(sprintf('%s%s is not a listener of %s', $class, $method !== null ? '::' . $method : '', $eventName));
    }
}
