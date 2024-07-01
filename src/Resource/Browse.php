<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Entity\Review;
use App\OpenApi\OpenApiFactory;
use App\State\BrowseStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/browse/authors/',
            formats: ['jsonld', 'json'],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['browse']],
                summary: 'Browse by author',
                description: 'Browse by author',
                parameters: [
                    new Parameter(
                        name: 'code',
                        in: 'query',
                        description: 'Journal code (exp. epijinfo)',
                        required: false,
                        schema: [
                            "type" => 'string',
                            "default" => ''
                        ]
                    ),

                    new Parameter(
                        name: 'letter', in: 'query',
                        description: 'Available values : A...Z, all',
                        required: false,
                        schema: [
                            "type" => 'string',
                            'default' => 'all',
                        ]
                    ),
                    new Parameter(
                        name: 'search', in: 'query',
                        description: 'Contains a given substring [Performs case-sensitive verification]',
                        required: false,
                        schema: [
                            "type" => 'string'
                        ]
                    ),
                    new Parameter(
                        name: 'sort',
                        in: 'query', schema: [
                        'type' => 'string',
                        'default' => 'index'
                    ]),
                ],
            ),
            normalizationContext: [
                'groups' => ['read:Browse:Authors']
            ],
        ),
        new GetCollection(
            uriTemplate: '/browse/authors-search/{author_fullname}',
            formats: ['jsonld', 'json'],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['browse']],
                summary: 'Author search by text',
                description: 'Search authors by text ',
                parameters: [
                    new Parameter(
                        name: 'code',
                        in: 'query',
                        description: 'Journal code (exp. epijinfo)',
                        required: false,
                        schema: [
                            "type" => 'string',
                            "default" => ''
                        ]
                    ),


                ],
            ),
            normalizationContext: [
                'groups' => ['read:Browse:Authors:fullName']
            ],
            output: SolrDoc::class,
        )

    ],
    output: Browse::class,
    provider: BrowseStateProvider::class
)]
class Browse
{
    private ?Review $journal = null;

    public function getJournal(): ?Review
    {
        return $this->journal;
    }

    public function setJournal(?Review $journal = null): self
    {
        $this->journal = $journal;
        return $this;
    }

}