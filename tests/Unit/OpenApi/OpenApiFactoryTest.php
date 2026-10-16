<?php

declare(strict_types=1);

namespace App\Tests\Unit\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use App\AppConstants;
use App\EventSubscriber\TruncatedCollectionSubscriber;
use App\OpenApi\OpenApiFactory;
use PHPUnit\Framework\TestCase;

class OpenApiFactoryTest extends TestCase
{
    private const string COLLECTION_PATH = '/api/papers/';
    private const string ITEM_PATH = '/api/papers/{docid}';

    public function testUnpaginatedCapIsDocumentedOnTheCollections(): void
    {
        $get = $this->createOpenApi()->getPaths()->getPath(self::COLLECTION_PATH)->getGet();

        $pagination = $get->getParameters()[0];
        $this->assertSame(
            sprintf(
                'Enable or disable pagination. Without pagination, at most %d items are returned: a truncated response holds the %s header.',
                AppConstants::MAXIMUM_ITEMS_PER_PAGE,
                TruncatedCollectionSubscriber::TRUNCATED_HEADER
            ),
            $pagination->getDescription()
        );

        $headers = $get->getResponses()[200]->getHeaders();
        $this->assertArrayHasKey(TruncatedCollectionSubscriber::TRUNCATED_HEADER, $headers);
        $this->assertSame(['type' => 'integer'], $headers[TruncatedCollectionSubscriber::TRUNCATED_HEADER]['schema']);
    }

    public function testOperationWithoutPaginationIsUnchanged(): void
    {
        $get = $this->createOpenApi()->getPaths()->getPath(self::ITEM_PATH)->getGet();

        $this->assertNull($get->getResponses()[200]->getHeaders());
    }

    private function createOpenApi(): OpenApi
    {
        $paths = new Paths();
        $paths->addPath(self::COLLECTION_PATH, new PathItem(get: new Operation(
            responses: [200 => new Response('Paper collection')],
            parameters: [new Parameter('pagination', 'query', 'Enable or disable pagination')]
        )));
        $paths->addPath(self::ITEM_PATH, new PathItem(get: new Operation(
            responses: [200 => new Response('Paper resource')],
            parameters: [new Parameter('docid', 'path', 'Paper identifier', true)]
        )));

        $decorated = $this->createStub(OpenApiFactoryInterface::class);
        $decorated->method('__invoke')->willReturn(new OpenApi(new Info('Episciences API', '1'), [], $paths, new Components(schemas: new \ArrayObject(), securitySchemes: new \ArrayObject())));

        return new OpenApiFactory($decorated)();
    }
}
