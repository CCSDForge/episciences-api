<?php

declare(strict_types=1);

namespace App\Metadata;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Declares the query parameters taking a single value as QueryParameter on the operations of the API, as if they
 * were declared in the attributes: API Platform rejects them with a 422 error when they are sent as a list
 * (`rvcode[]=…`), before the data is read, instead of failing later as a server error or of being silently ignored.
 *
 * The validation runs on every operation, `read: false` included (it decorates the read provider, which checks `read`
 * afterwards). The parameters keep their own documentation; the collections declare the validation error, so that
 * API Platform documents the 422 with its schema (OpenApiFactory describes which parameters it concerns).
 */
final readonly class SingleValueQueryParametersResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /** Journal code (a single journal is read from it), search terms, first letter of the authors and accepted papers only */
    public const array PARAMETERS = ['code', 'rvcode', 'terms', 'letter', 'only_accepted'];

    /** Parameters of the pagination, on the collections */
    public const array PAGINATION_PARAMETERS = ['page', 'itemsPerPage', 'pagination'];

    /** Extra property marking the QueryParameter added here (their value is checked to be a single one) */
    public const string SINGLE_VALUE = 'app_single_value';

    public function __construct(private ResourceMetadataCollectionFactoryInterface $decorated)
    {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        // The resources of API Platform (e.g. its errors) are rendered by a sub-request with the same query
        if (!str_starts_with($resourceClass, 'App\\')) {
            return $resourceMetadataCollection;
        }

        foreach ($resourceMetadataCollection as $index => $resource) {
            $operations = $resource->getOperations();

            if (!$operations instanceof Operations) {
                continue;
            }

            foreach ($operations as $name => $operation) {
                $operations->add($name, $this->withSingleValueParameters($operation));
            }

            $resourceMetadataCollection[$index] = $resource->withOperations($operations);
        }

        return $resourceMetadataCollection;
    }

    private function withSingleValueParameters(Operation $operation): Operation
    {
        $parameters = $operation->getParameters() instanceof Parameters ? clone $operation->getParameters() : new Parameters();
        $names = $operation instanceof CollectionOperationInterface ? [...self::PARAMETERS, ...self::PAGINATION_PARAMETERS] : self::PARAMETERS;

        foreach ($names as $name) {
            // a parameter declared by the operation is kept (e.g. its schema validates it)
            if (!$parameters->has($name)) {
                $parameters->add($name, new QueryParameter(key: $name, openApi: false, hydra: false, constraints: [new Type('string')], extraProperties: [self::SINGLE_VALUE => true]));
            }
        }

        $operation = $operation->withParameters($parameters);

        // On an item, declaring an error would remove the 404 documented by default by API Platform:
        // OpenApiFactory gives it the 422 documented on the collections
        if (!$operation instanceof HttpOperation || !$operation instanceof CollectionOperationInterface) {
            return $operation;
        }

        return $operation->withErrors(array_values(array_unique([...$operation->getErrors() ?? [], ValidationException::class])));
    }
}
