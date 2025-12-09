<?php

namespace App\Serializer\Normalizer;

use App\Service\Solarium\Client;
use App\Service\Solr;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AbstractNormalizer
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        protected NormalizerInterface $decorated,
        protected EntityManagerInterface $entityManager,
        protected Solr $solrService,
        protected Client $search,
        protected ?LoggerInterface $logger,
        protected  Security $security

    ) {
    }
}
