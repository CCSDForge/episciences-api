<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\AppConstants;
use App\State\StatisticStateProcessor;
use App\State\StatisticStateProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [

        new GetCollection(
            uriTemplate: '/statistics/',
            openapi: new OpenApiOperation(
                tags: ['Statistics | UX'],
                summary: "Retrieve all available statistical indicators",
                description: "",
                parameters: [
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
                        name: 'indicator',
                        in: 'query',
                        description: "Statistic's identifier (exp. nb-submissions)",
                        required: false,
                        schema: [
                            "type" => 'string',
                            "default" => ''
                        ]
                    ),
                    new Parameter(
                        name: 'indicator[]',
                        in: 'query',
                        description: "Statistic's identifier (exp. nb-submissions)",
                        required: false,
                        schema: [
                            "type" => 'array',
                            'items' => [
                                'type' => 'string',
                            ]
                        ],
                        explode: true,
                        allowReserved: false,
                    ),
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: 'year[]',
                        in: 'query',
                        description: 'The Year of submission',
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
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: this parameter is ignored if the format is wrong',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    ),
                ]
            ),
        ),

        new Get(
            uriTemplate: '/statistics/nb-submissions',
            openapi: new OpenApiOperation(
                tags: ['Statistics | UX'],
                summary: "Total number of submissions",
                description: "",
                parameters: [
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
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: 'year[]',
                        in: 'query',
                        description: 'The Year of submission',
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
                        name: AppConstants::PAPER_STATUS,
                        in: 'query',
                        description: "Article's status",
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'string',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: 'status[]',
                        in: 'query',
                        description: "Article's status",
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
                        name: AppConstants::PAPER_FLAG,
                        in: 'query',
                        description: 'flag to be applied (accepted values: imported, submitted)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'string',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: this parameter is ignored if the format is wrong',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    ),
                ]
            ),

        ),
        new Get(
            uriTemplate: '/statistics/median-submission-publication',
            openapi: new OpenApiOperation(
                tags: ['Statistics | UX'],
                summary: "Median Time submission-publication",
                description: self::INDICATOR_DESCRIPTION,
                parameters: [
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
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: 'year[]',
                        in: 'query',
                        description: 'The Year of submission',
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
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: this parameter is ignored if the format is wrong',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    ),
                    new Parameter(
                        name: 'unit',
                        in: 'query',
                        description: 'The unit of the difference of two timestamps in MySQL',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'string',
                            'default' => 'week'
                        ],
                        explode: false,
                        allowReserved: false
                    )
                ]
            ),
        ),

        new Get(
            uriTemplate: '/statistics/median-submission-acceptance',
            openapi: new OpenApiOperation(
                tags: ['Statistics | UX'],
                summary: "Median Time submission-acceptance",
                description: self::INDICATOR_DESCRIPTION,
                parameters: [
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
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: 'year[]',
                        in: 'query',
                        description: 'The Year of submission',
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
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: this parameter is ignored if the format is wrong',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    ),
                    new Parameter(
                        name: 'unit',
                        in: 'query',
                        description: 'The unit of the difference of two timestamps in MySQL',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'string',
                            'default' => 'week'
                        ],
                        explode: false,
                        allowReserved: false
                    )
                ]
            ),
        ),


        new Get(
            uriTemplate: '/statistics/acceptance-rate',
            openapi: new OpenApiOperation(
                tags: ['Statistics | UX'],
                summary: "Acceptance rate",
                description: self::INDICATOR_DESCRIPTION,
                parameters: [
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
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: 'year[]',
                        in: 'query',
                        description: 'The Year of submission',
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
                        name: AppConstants::START_AFTER_DATE,
                        in: 'query',
                        description: 'Start statistics after date [YYYY-MM-DD]: this parameter is ignored if the format is wrong',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                    ),
                ]
            ),
        ),

        new GetCollection(
            uriTemplate: '/statistics/evaluation',
            openapi: new OpenApiOperation(
                tags: ['Statistics | UX'],
                summary: "Evaluation",
                description: 'Evaluation',
                parameters: [
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
                        name: 'indicator',
                        in: 'query',
                        description: "Statistic's identifier (exp. nb-submissions)",
                        required: false,
                        schema: [
                            "type" => 'string',
                            "default" => ''
                        ]
                    ),
                    new Parameter(
                        name: 'indicator[]',
                        in: 'query',
                        description: "Statistic's identifier (exp. nb-submissions)",
                        required: false,
                        schema: [
                            "type" => 'array',
                            'items' => [
                                'type' => 'string',
                            ]
                        ],
                        explode: true,
                        allowReserved: false,
                    ),
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: 'The Year of submission',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'integer',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: 'year[]',
                        in: 'query',
                        description: 'The Year of submission',
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
                ]
            ),
        ),
    ],
    normalizationContext: [
        'groups' => [
            'read:Statistic'
        ]

    ],
    provider: StatisticStateProvider::class,
    processor: StatisticStateProcessor::class

)]
class Statistic
{
    public const INDICATOR_DESCRIPTION = "/!\ This indicator excludes imported articles";
    public const STATISTIC_GET_COLLECTION_OPERATION_IDENTIFIER = '/api/statistics/';
    public const EVALUATION_GET_COLLECTION_OPERATION_IDENTIFIER = '/api/statistics/evaluation';

    public const EVAL_INDICATORS = [
        'median-reviews-number_get' => 'median-reviews-number',
        'reviews-requested_get' => 'reviews-requested',
        'reviews-received_get' => 'reviews-received',
    ];
    public const AVAILABLE_PUBLICATION_INDICATORS = [
        'nb-submissions_get' => 'nb-submissions',
        'acceptance-rate_get' => 'acceptance-rate',
        'median-submission-publication_get' => 'median-submission-publication',
        'median-submission-acceptance_get' => 'median-submission-acceptance',
        'evaluation_get_collection' => 'evaluation'
    ];
    #[groups(['read:Statistic'])]
    private string $name;
    #[groups(['read:Statistic'])]
    private array|float|null $value;
    #[groups(['read:Statistic'])]
    private string|null $unit;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getValue(): array|float|null
    {
        return $this->value;
    }

    public function setValue(array|float|null $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;
        return $this;
    }


}