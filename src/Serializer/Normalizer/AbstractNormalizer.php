<?php

namespace App\Serializer\Normalizer;

use App\Service\Solr;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AbstractNormalizer
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        protected NormalizerInterface $decorated,
        protected EntityManagerInterface $entityManager,
        protected Solr $solrService

    ) {
    }
}