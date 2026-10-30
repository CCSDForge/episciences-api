<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\State\ApiResource\Error;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Paper;
use App\Metadata\SingleValueQueryParametersResourceMetadataCollectionFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * The query parameters taking a single value are declared as QueryParameter, validated natively by API Platform (422).
 */
class SingleValueQueryParametersResourceMetadataCollectionFactoryTest extends TestCase
{
    public function testCollectionGetsTheSingleValueAndPaginationParameters(): void
    {
        $parameters = $this->operation(Paper::class, new GetCollection(name: 'collection'))->getParameters();

        foreach ([...SingleValueQueryParametersResourceMetadataCollectionFactory::PARAMETERS, ...SingleValueQueryParametersResourceMetadataCollectionFactory::PAGINATION_PARAMETERS] as $name) {
            $parameter = $parameters?->get($name);

            $this->assertInstanceOf(QueryParameter::class, $parameter, $name);
            $this->assertSame($name, $parameter->getKey());
            // documented by the operation itself
            $this->assertFalse($parameter->getOpenApi());
            $this->assertFalse($parameter->getHydra());
            $this->assertTrue($parameter->getExtraProperties()[SingleValueQueryParametersResourceMetadataCollectionFactory::SINGLE_VALUE]);
        }
    }

    public function testCollectionDeclaresTheValidationError(): void
    {
        $operation = $this->operation(Paper::class, new GetCollection(name: 'collection', errors: [\RuntimeException::class]));

        $this->assertInstanceOf(HttpOperation::class, $operation);
        $this->assertSame([\RuntimeException::class, ValidationException::class], $operation->getErrors());
    }

    /**
     * Declaring an error on an item would remove the 404 documented by default by API Platform
     */
    public function testItemDoesNotDeclareTheValidationError(): void
    {
        $operation = $this->operation(Paper::class, new Get(name: 'item'));

        $this->assertInstanceOf(HttpOperation::class, $operation);
        $this->assertNull($operation->getErrors());
    }

    public function testItemDoesNotGetThePaginationParameters(): void
    {
        $parameters = $this->operation(Paper::class, new Get(name: 'item'))->getParameters();

        $this->assertTrue($parameters?->has('rvcode'));
        $this->assertFalse($parameters->has('page'));
    }

    public function testParameterDeclaredByTheOperationIsKept(): void
    {
        $declared = new QueryParameter(key: 'only_accepted', schema: ['type' => 'boolean']);
        $operation = $this->operation(Paper::class, new GetCollection(name: 'collection', parameters: ['only_accepted' => $declared]));

        $this->assertSame($declared, $operation->getParameters()?->get('only_accepted'));
    }

    public function testResourceOfApiPlatformIsUnchanged(): void
    {
        $operation = $this->operation(Error::class, new Get(name: 'error'));

        $this->assertNull($operation->getParameters());
    }

    public function testListIsRejectedAndSingleValueAccepted(): void
    {
        $constraints = $this->operation(Paper::class, new GetCollection(name: 'collection'))->getParameters()?->get('rvcode')?->getConstraints();
        $validator = Validation::createValidator();

        $this->assertCount(1, $validator->validate(['dev'], $constraints));
        $this->assertCount(0, $validator->validate('dev', $constraints));
        // parameter absent
        $this->assertCount(0, $validator->validate(null, $constraints));
    }

    private function operation(string $resourceClass, Operation $operation): Operation
    {
        $decorated = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $decorated->method('create')->willReturn(new ResourceMetadataCollection($resourceClass, [
            new ApiResource(operations: [$operation->getName() => $operation]),
        ]));

        $resource = new SingleValueQueryParametersResourceMetadataCollectionFactory($decorated)->create($resourceClass)[0];

        return iterator_to_array($resource->getOperations())[$operation->getName()];
    }
}
