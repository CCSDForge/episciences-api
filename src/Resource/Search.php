<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\OpenApi\OpenApiFactory;
use App\State\SearchStateProvider;


#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/search/',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['browse_search']],
                summary: 'Document search',
                description: '',
                parameters: [
                    new Parameter(
                        name: self::TERMS_PARAM,
                        in: 'query',
                        description: 'Search terms',
                        required: true,
                        schema: [
                            "type" => 'string',
                            "default" => self::DEFAULT_TERMS,
                        ]
                    ),
                    new Parameter(
                        name: 'rvcode',
                        in: 'query',
                        description: 'Journal code (exp. epijinfo)',
                        required: false,
                        schema: [
                            "type" => 'string',
                            "default" => ''
                        ]
                    ),
                    new Parameter(
                        name: self::DOC_TYPE_FILTER,
                        in: 'query',
                        description: 'Document type',
                        required: false,
                        deprecated: false,
                        schema: [
                            "type" => 'string'
                        ]
                    ),
                    new Parameter(
                        name: self::ARRAY_DOC_TYPE_FILTER,
                        in: 'query',
                        description: 'Document type',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ]
                        ],
                        explode: true,
                        allowReserved: false,

                    ),
                    new Parameter(
                        name: self::VOLUME_FILTER,
                        in: 'query',
                        description: 'Volume identifier',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                        explode: false,
                        allowReserved: false,

                    ),
                    new Parameter(
                        name: self::ARRAY_VOLUME_FILTER,
                        in: 'query',
                        description: 'Volume identifier',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ]
                        ],
                        explode: true,
                        allowReserved: false,

                    ),
                    new Parameter(
                        name: self::SECTION_FILTER,
                        in: 'query',
                        description: 'Section identifier',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                        explode: false,
                        allowReserved: false,

                    ),
                    new Parameter(
                        name: self::ARRAY_SECTION_FILTER,
                        in: 'query',
                        description: 'Section identifier',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ]
                        ],
                        explode: true,
                        allowReserved: false,

                    ),

                    new Parameter(
                        name: self::PUBLICATION_DATE_YEAR_FILTER,
                        in: 'query',
                        description: 'Publication year',
                        required: false,
                        deprecated: false,
                        schema: [
                            "type" => 'integer'
                        ]
                    ),
                    new Parameter(
                        name: self::ARRAY_PUBLICATION_DATE_YEAR_FILTER,
                        in: 'query',
                        description: 'Publication year',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ]
                        ],
                        explode: true,
                        allowReserved: false,

                    ),

                    new Parameter(
                        name: self::AUTHOR_FULL_NAME_FILTER,
                        in: 'query',
                        description: "Author's full name",
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'string',
                        ],
                        explode: false,
                        allowReserved: false,

                    ),
                    new Parameter(
                        name: self::ARRAY_AUTHOR_FULL_NAME_FILTER,
                        in: 'query',
                        description: "Author's full name",
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ]
                        ],
                        explode: true,
                        allowReserved: false
                    ),

                ],
            ),
            normalizationContext: [
                'groups' => ['read:Search']
            ],
            output: SolrDoc::class,
        )

    ],

    provider: SearchStateProvider::class
)]
class Search
{
    public const DEFAULT_TERMS = '*:*';
    public const TERMS_PARAM = 'terms';
    public const SECTION_FILTER = 'section_id';
    public const AUTHOR_FULL_NAME_FILTER = 'author_fullname';
    public const DOC_TYPE_FILTER = 'type';
    public const PUBLICATION_DATE_YEAR_FILTER = 'year';
    public const VOLUME_FILTER = 'volume_id';
    public const VOLUME_FACET_NAME = 'volume';
    public const SECTION_FACET_NAME = 'section';
    public const AUTHOR_FACET_NAME = 'author';


    public const SEARCH_FILTERS_MAPPING = [
        self::SECTION_FILTER => 'section_id_i',
        self::VOLUME_FILTER => 'volume_id_i',
        self::DOC_TYPE_FILTER => 'doc_type_fs',
        self::AUTHOR_FULL_NAME_FILTER => 'author_fullname_t',
        self::PUBLICATION_DATE_YEAR_FILTER => 'publication_date_year_fs'

    ];




    public const ARRAY_SECTION_FILTER = 'section_id[]';
    public const ARRAY_AUTHOR_FULL_NAME_FILTER = 'author_fullname[]';
    public const ARRAY_DOC_TYPE_FILTER = 'type[]';
    public const ARRAY_PUBLICATION_DATE_YEAR_FILTER = 'year[]';
    public const ARRAY_VOLUME_FILTER = 'volume_id[]';


}