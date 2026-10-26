<?php

declare(strict_types=1);

namespace App\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\OpenApi\OpenApiFactory;
use App\State\BrowseStateProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/browse/authors/',
            formats: ['jsonld', 'json'],
            openapi: new OpenApiOperation(
                operationId: 'api_browse_authors_get_collection',
                tags: [OpenApiFactory::OAF_TAGS['browse_search']],
                summary: 'Browse by authors',
                description: self::BROWSE_AUTHORS_DESCRIPTION,
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
                        description: self::AVAILABLE_VALUES_TO_PREFIX_DESCRIPTION,
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
                        in: 'query',
                        description: 'Alphabetically [index] | Total articles DESC [count]',
                        schema: [
                        'type' => 'string',
                        'default' => 'index'
                    ]),
                ],
            ),
            normalizationContext: [
                'groups' => ['read:Browse:Authors']
            ],
            output: Facet::class,
        ),
        new GetCollection(
            uriTemplate: '/browse/authors-search/{author_fullname}',
            formats: ['jsonld', 'json'],
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['browse_search']],
                summary: 'Author search by text',
                description: self::SEARCH_AUTHORS_DESCRIPTION,
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
class Browse extends AbstractBrowse
{
    public const string BROWSE_AUTHORS_DESCRIPTION = 'Browse the authors of published papers, with their number of articles. Filter by journal code, first letter or substring; sort alphabetically or by number of articles.';
    public const string SEARCH_AUTHORS_DESCRIPTION = 'Published documents of the authors whose full name matches the given text, optionally in one journal.';
    public const AVAILABLE_VALUES_TO_PREFIX_DESCRIPTION = 'Prefixed with a letter: available values [A...Z, others, all]';
    public const BROWSE_AUTHORS_COLLECTION_IDENTIFIER = '/api/browse/authors/';
}
