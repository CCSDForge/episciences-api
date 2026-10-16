<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Doctrine\PaperCollectionPartialExtension;
use App\Entity\Paper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Without pagination (?pagination=false), the Paper collection is capped to the
 * maximum number of items per page: PaperCollectionPartialExtension fetches one
 * paper more, which is removed here, and the cap is stored in the request
 * (TRUNCATED_ATTRIBUTE) so that the response can tell the client.
 *
 * @implements ProviderInterface<Paper>
 */
final class PaperCollectionProvider implements ProviderInterface
{
    public const string TRUNCATED_ATTRIBUTE = '_papers_truncated_max_items';

    /**
     * @param ProviderInterface<Paper> $collectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $collectionProvider
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $papers = $this->collectionProvider->provide($operation, $uriVariables, $context);

        if (!is_array($papers)) { // paginated
            return $papers;
        }

        $maxItems = PaperCollectionPartialExtension::getMaxItemsWithoutPagination($operation);

        if (count($papers) <= $maxItems) {
            return $papers;
        }

        $request = $context['request'] ?? null;

        if ($request instanceof Request) {
            $request->attributes->set(self::TRUNCATED_ATTRIBUTE, $maxItems);
        }

        return array_slice($papers, 0, $maxItems);
    }
}
