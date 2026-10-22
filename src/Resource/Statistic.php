<?php

namespace App\Resource;

use App\OpenApi\OpenApiSchemas;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\AppConstants;
use App\OpenApi\OpenApiFactory;
use App\State\StatisticStateProvider;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

#[ApiResource(
    operations: [

        new GetCollection(
            uriTemplate: '/statistics/',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Retrieve all available statistical indicators",
                description: 'Statistics of all the journals, or of one journal with `rvcode`. ' . self::SEE_STATISTICS_SECTION,
                parameters: [
                    new Parameter(
                        name: 'rvcode',
                        in: 'query',
                        description: 'Journal code (exp. epijinfo)',
                        required: false,
                        schema: [
                            "type" => 'string'
                        ]
                    ),
                    new Parameter(
                        name: 'indicator',
                        in: 'query',
                        description: 'A single indicator; all of them when omitted',
                        required: false,
                        schema: [
                            "type" => 'string',
                            'enum' => self::PUBLICATION_INDICATOR_VALUES,
                        ]
                    ),
                    new Parameter(
                        name: 'indicator[]',
                        in: 'query',
                        description: 'Same as `indicator`, sent as an array: a single value',
                        required: false,
                        schema: [
                            "type" => 'array',
                            'items' => [
                                'type' => 'string',
                                'enum' => self::PUBLICATION_INDICATOR_VALUES,
                            ]
                        ],
                        explode: true,
                        allowReserved: false,
                    ),
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: self::YEAR_DESCRIPTION,
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
                        description: self::YEARS_DESCRIPTION,
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
                        description: 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (404)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ),
                    new Parameter(
                        name: 'unit',
                        in: 'query',
                        description: 'The unit of the median delays (difference of two timestamps in MySQL)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'string',
                            'enum' => StatisticStateProvider::AVAILABLE_UNITS,
                            'default' => 'week'
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: self::GROUP_BY_PARAM,
                        in: 'query',
                        description: 'Returns the median of each journal (one item by journal, its code in `journal`). Only with `indicator=median-submission-publication` or `indicator=median-submission-acceptance`, and without `rvcode`.',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'string',
                            'enum' => [self::GROUP_BY_JOURNAL],
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                ]
            ),
        ),

        new Get(
            uriTemplate: '/statistics/nb-submissions',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Total number of submissions",
                description: self::SEE_STATISTICS_SECTION,
                parameters: [
                    new Parameter(
                        name: 'rvcode',
                        in: 'query',
                        description: 'Journal code (exp. epijinfo)',
                        required: false,
                        schema: [
                            "type" => 'string'
                        ]
                    ),
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: self::YEAR_DESCRIPTION,
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
                        description: self::YEARS_DESCRIPTION,
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
                        description: 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (404)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ),
                ]
            ),

        ),
        new Get(
            uriTemplate: '/statistics/median-submission-publication',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Median Time submission-publication",
                description: self::INDICATOR_DESCRIPTION . ' ' . self::SEE_STATISTICS_SECTION,
                parameters: [
                    new Parameter(
                        name: 'rvcode',
                        in: 'query',
                        description: 'Journal code (exp. epijinfo)',
                        required: false,
                        schema: [
                            "type" => 'string'
                        ]
                    ),
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: self::YEAR_DESCRIPTION,
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
                        description: self::YEARS_DESCRIPTION,
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
                        description: 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (404)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
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
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Median Time submission-acceptance",
                description: self::INDICATOR_DESCRIPTION . ' ' . self::SEE_STATISTICS_SECTION,
                parameters: [
                    new Parameter(
                        name: 'rvcode',
                        in: 'query',
                        description: 'Journal code (exp. epijinfo)',
                        required: false,
                        schema: [
                            "type" => 'string'
                        ]
                    ),
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: self::YEAR_DESCRIPTION,
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
                        description: self::YEARS_DESCRIPTION,
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
                        description: 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (404)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
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
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Acceptance rate",
                description: self::INDICATOR_DESCRIPTION . ' ' . self::SEE_STATISTICS_SECTION,
                parameters: [
                    new Parameter(
                        name: 'rvcode',
                        in: 'query',
                        description: 'Journal code (exp. epijinfo)',
                        required: false,
                        schema: [
                            "type" => 'string'
                        ]
                    ),
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: self::YEAR_DESCRIPTION,
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
                        description: self::YEARS_DESCRIPTION,
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
                        description: 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (404)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ),
                ]
            ),
        ),

        new GetCollection(
            uriTemplate: '/statistics/evaluation',
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['stats']],
                summary: "Evaluation",
                description: 'Reviews of the articles. ' . self::SEE_STATISTICS_SECTION,
                parameters: [
                    new Parameter(
                        name: 'rvcode',
                        in: 'query',
                        description: 'Journal code (exp. epijinfo)',
                        required: false,
                        schema: [
                            "type" => 'string'
                        ]
                    ),
                    new Parameter(
                        name: 'indicator',
                        in: 'query',
                        description: 'A single indicator; all of them when omitted',
                        required: false,
                        schema: [
                            "type" => 'string',
                            'enum' => self::EVAL_INDICATOR_VALUES,
                        ]
                    ),
                    new Parameter(
                        name: 'indicator[]',
                        in: 'query',
                        description: 'Same as `indicator`, sent as an array: a single value',
                        required: false,
                        schema: [
                            "type" => 'array',
                            'items' => [
                                'type' => 'string',
                                'enum' => self::EVAL_INDICATOR_VALUES,
                            ]
                        ],
                        explode: true,
                        allowReserved: false,
                    ),
                    new Parameter(
                        name: AppConstants::YEAR_PARAM,
                        in: 'query',
                        description: self::YEAR_DESCRIPTION,
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
                        description: self::YEARS_DESCRIPTION,
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

)]
class Statistic
{
    public const INDICATOR_DESCRIPTION = "/!\ This indicator excludes imported articles.";
    public const SEE_STATISTICS_SECTION = 'See the description of the Statistics section for the date filtered by `year` and the definitions.';
    public const YEAR_DESCRIPTION = 'Year. It does not filter every statistic on the same date: see "Which date does `year` filter on?" in the description of the Statistics section.';
    /** Common to all the statistics operations: description of the Statistics tag (see OpenApiFactory) */
    public const STATISTICS_DESCRIPTION = 'Statistics of the journals: `/api/statistics/*` (all the journals, or one journal with `rvcode`) and `/api/journals/stats/*` (one journal).'
        . "\n\n#### Which date does `year` filter on?\n\n"
        . '`year` (`year[]` for several years) does not filter every statistic on the same date:'
        . "\n\n| Statistic | `year` is the year of |\n|---|---|\n"
        . "| Submissions: `nb-submissions`, `nb-submitted-versions`, `nb-imported-versions`, `nbSubmissions`, `nbImported` | the submission |\n"
        . "| Articles awaiting a decision: `being-to-publish.other-status` | the submission |\n"
        . "| Published articles: `published`, `nbPublished`, `nbImportedPublished` | the publication |\n"
        . "| Accepted articles: `accepted`, `being-to-publish.accepted`, `nbAccepted`, `nbAcceptedNotYetPublished` | the acceptance |\n"
        . "| Refused articles: `refused`, `nbRefused` | the refusal |\n"
        . "| Rates: `acceptance-rate`, `rates` | the acceptances, refusals and publications |\n"
        . "| Submission-acceptance delays: `median-submission-acceptance`, `delay-submission-acceptance`, `submissionAcceptanceTime`, `submissionAcceptanceTimeMedian` | the acceptance |\n"
        . "| Submission-publication delays: `median-submission-publication`, `delay-submission-publication`, `submissionPublicationTime`, `submissionPublicationTimeMedian` | the publication |\n"
        . "| Submitted and decided the same year: `totalAcceptedSubmittedSameYear`, `totalPublishedSubmittedSameYear`, `totalRefusedSubmittedSameYear` | both the submission and the decision |\n"
        . "| Dashboard `nbOtherStatus` | the submission, minus the acceptances and refusals of the year |\n"
        . "| Dashboard `totalOtherSubmittedSameYear` | the submission, minus the articles accepted or refused the same year |\n"
        . "| Reviews requested: `reviews-requested` | the sending of the invitation |\n"
        . "| Reviews received: `reviews-received`, `median-reviews-number` | the creation of the report |\n"
        . "| Users: `nb-users`, `nbUsers` | not available by year |\n\n"
        . 'For example, with `year=2024`, `nb-submissions` is the number of articles **submitted** in 2024, and `median-submission-publication` the median delay of the articles **published** in 2024, whatever their year of submission.'
        . "\n\n" . '`startAfterDate` keeps only the articles submitted after this date (not applied to the reviews).'
        . "\n\n#### Delays\n\n" . 'The delay of an article runs from its first submission to its latest publication (or acceptance) recorded in its history, and is truncated to the unit: 13 days count as 1 week. Imported articles are excluded, as for the rates. In `/api/statistics/*`, `count` is the number of articles a median is computed from, and `groupBy=journal` returns the median of each journal having at least one article.'
        . "\n\n#### Articles awaiting a decision\n\n" . '`being-to-publish.other-status` counts the submitted articles (not imported) still awaiting an editorial decision: neither published, accepted nor refused (decisions read from their history, as for `accepted` and `refused`), deleted nor removed. An abandoned article, only paused, is counted.';
    /** Values of the "indicator" parameter (lists for the OpenAPI enums) */
    public const PUBLICATION_INDICATOR_VALUES = [
        self::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get'],
        self::AVAILABLE_PUBLICATION_INDICATORS['acceptance-rate_get'],
        self::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get'],
        self::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get'],
        self::AVAILABLE_PUBLICATION_INDICATORS['evaluation_get_collection'],
    ];
    public const EVAL_INDICATOR_VALUES = [
        self::EVAL_INDICATORS['reviews-requested_get'],
        self::EVAL_INDICATORS['reviews-received_get'],
        self::EVAL_INDICATORS['median-reviews-number_get'],
    ];
    public const YEARS_DESCRIPTION = 'Several years, with the same meaning as `year`.';
    public const GROUP_BY_PARAM = 'groupBy';
    public const GROUP_BY_JOURNAL = 'journal';
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
    #[ApiProperty(schema: [
        'description' => 'A number, or an object detailing the indicator for "nb-submissions-details" and "evaluation"',
        'example' => 43.72,
    ] + OpenApiSchemas::STAT_VALUE)]
    private array|float|null $value = null;
    #[groups(['read:Statistic'])]
    private string|null $unit = null;
    #[groups(['read:Statistic'])]
    #[Context(normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    #[ApiProperty(description: 'Code of the journal, with `groupBy=journal`', schema: ['type' => 'string', 'example' => 'epijinfo'])]
    private ?string $journal = null;
    #[groups(['read:Statistic'])]
    #[Context(normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    #[ApiProperty(description: 'Number of articles the median delay is computed from (median delays only)', schema: ['type' => 'integer', 'minimum' => 0, 'example' => 412])]
    private ?int $count = null;

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

    public function getJournal(): ?string
    {
        return $this->journal;
    }

    public function setJournal(?string $journal): self
    {
        $this->journal = $journal;
        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(?int $count): self
    {
        $this->count = $count;
        return $this;
    }


}
