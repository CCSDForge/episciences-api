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
                description: 'Statistics of all the journals, or of one journal with `rvcode`. ' . self::OTHER_STATUS_DESCRIPTION,
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
                        description: 'Indicator; all of them when omitted',
                        required: false,
                        schema: [
                            "type" => 'string',
                            'enum' => self::PUBLICATION_INDICATOR_VALUES,
                        ]
                    ),
                    new Parameter(
                        name: 'indicator[]',
                        in: 'query',
                        description: 'Several indicators, returned in this order',
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
                        description: self::YEAR_COLLECTION_DESCRIPTION,
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
                        description: 'Several years, with the same meaning as `year`; an article decided in several of them is counted once in the total',
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
                        name: self::WITH_PAPERS_PARAM,
                        in: 'query',
                        description: self::WITH_PAPERS_DESCRIPTION,
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'boolean',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: self::GROUP_BY_PARAM,
                        in: 'query',
                        description: self::GROUP_BY_DESCRIPTION,
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
                description: 'Total number of submitted articles, for all the journals or for one journal with `rvcode`. Filter by year(s) of submission, by status and by flag; the imported articles are excluded. Returns the figure and, with `withDetails`, the number of articles in each status.',
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
                        description: 'Year of submission of the articles',
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
                        description: 'Several years of submission',
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
                description: self::INDICATOR_DESCRIPTION . ' ' . self::MEDIAN_DELAY_DESCRIPTION,
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
                        description: 'Year of publication of the articles',
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
                        description: 'Several years of publication',
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
                description: self::INDICATOR_DESCRIPTION . ' ' . self::MEDIAN_DELAY_DESCRIPTION,
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
                        description: 'Year of acceptance of the articles',
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
                        description: 'Several years of acceptance',
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
                description: self::INDICATOR_DESCRIPTION,
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
                        description: 'Year of the decision (acceptance or refusal)',
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
                        description: 'Several years of decision; an article decided in several of them is counted once',
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
                description: 'Statistics of the editorial evaluation: the number of reviews requested (`reviews-requested`: the invitations sent to the reviewers for a paper, whatever their answer, a pending invitation past its expiration date included, except the cancelled ones), the number of reviews received (`reviews-received`: the completed reports, dated by their validation) and the median number of reviews per reviewed article (`median-reviews-number`: the completed reports of each article, all its versions together, among the articles having at least one), for all the journals or for one journal with `rvcode`. Filter by year(s) of invitation or of validation of the report; `groupBy=journal` returns one figure by journal.',
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
                        description: 'Indicator; all of them when omitted',
                        required: false,
                        schema: [
                            "type" => 'string',
                            'enum' => self::EVAL_INDICATOR_VALUES,
                        ]
                    ),
                    new Parameter(
                        name: 'indicator[]',
                        in: 'query',
                        description: 'Several indicators, returned in this order',
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
                        description: 'Year of the invitation (reviews-requested) or of the validation of the report (reviews-received, median-reviews-number)',
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
                        description: 'Several years, with the same meaning as `year`',
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
                        description: 'Start statistics after date [YYYY-MM-DD], on the date of the invitation or of the validation of the report: a wrong format is rejected (404)',
                        required: false,
                        deprecated: false,
                        allowEmptyValue: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ),
                    new Parameter(
                        name: self::WITH_PAPERS_PARAM,
                        in: 'query',
                        description: self::WITH_REVIEWS_DESCRIPTION,
                        required: false,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            'type' => 'boolean',
                        ],
                        explode: false,
                        allowReserved: false
                    ),
                    new Parameter(
                        name: self::GROUP_BY_PARAM,
                        in: 'query',
                        description: self::GROUP_BY_DESCRIPTION,
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
    public const MEDIAN_DELAY_DESCRIPTION = 'The delay runs from the first submission of the article to its latest publication (or acceptance), truncated to the unit (13 days = 1 week). `count` is the number of articles.';
    public const OTHER_STATUS_DESCRIPTION = 'In `nb-submissions-details`, `being-to-publish.other-status` is the number of articles awaiting an editorial decision (neither published, accepted, refused, deleted nor removed; an abandoned article is only paused).';
    public const YEAR_COLLECTION_DESCRIPTION = 'Year of: submission for `nb-submissions` (and the submitted, imported and awaiting-decision articles of `nb-submissions-details`); decision for `acceptance-rate` (and the published, accepted and refused articles); publication or acceptance for the median delays; invitation or validation of the report for `evaluation`';
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
    public const GROUP_BY_DESCRIPTION = 'Returns the statistics of each active journal: one item by journal and by indicator, with the journal code in `journal`, sorted by code. Only the journals having data are returned. Not with `rvcode`';
    public const NB_SUBMISSIONS_DETAILS = 'nb-submissions-details';
    public const WITH_PAPERS_PARAM = 'withPapers';
    public const WITH_PAPERS_DESCRIPTION = 'Adds `papers`, the papers counted by each statistic: one DOCID by paper, its latest version, computed with the same queries as the figures. With `indicator=evaluation`, `reviews-requested` and `reviews-received` get `reviews` instead, as on /api/statistics/evaluation; the `evaluation` statistic returned without `indicator` gets no list. Only with `rvcode`, without `groupBy`, for the administrators of the journal (JWT); the papers for which the user has declared a conflict of interest are not listed, their number is in `hiddenPapers` (`hiddenReviews` for the reviews)';
    public const WITH_REVIEWS_DESCRIPTION = 'Adds `reviews` to `reviews-requested` and `reviews-received`: the invitations or the completed reports counted, without the reviewers, computed with the same queries as the figures, sorted by DOCID then date. The lists are not paginated: all the reviews counted are returned, use `year` or `startAfterDate` to limit them. Only with `rvcode`, without `groupBy`, for the administrators of the journal (JWT); the reviews of the papers for which the user has declared a conflict of interest are not listed, their number is in `hiddenReviews`';
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
    #[groups(['read:Statistic'])]
    #[Context(normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    #[ApiProperty(description: 'With `withPapers`: the papers counted (one DOCID by paper, its latest version), or, for a statistic made of several figures, the papers of each figure', schema: ['type' => ['array', 'object'], 'items' => ['type' => 'integer'], 'example' => [18910, 18947]])]
    private ?array $papers = null;
    #[groups(['read:Statistic'])]
    #[Context(normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    #[ApiProperty(description: 'With `withPapers`: the number of papers not listed in `papers` because of a conflict of interest (by figure for a statistic made of several figures)', schema: ['type' => ['integer', 'object'], 'example' => 0])]
    private int|array|null $hiddenPapers = null;
    #[groups(['read:Statistic'])]
    #[Context(normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    #[ApiProperty(description: 'With `withPapers`, for `reviews-requested` and `reviews-received`: the reviews counted, without the reviewers: one item by invitation (`date` of sending, `answer`: pending, accepted, declined or expired) or by completed report (`date` of validation), with the DOCID of the paper version', schema: ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['docid' => ['type' => 'integer'], 'date' => ['type' => ['string', 'null'], 'format' => 'date'], 'answer' => ['type' => 'string', 'enum' => ['pending', 'accepted', 'declined', 'expired']]]], 'example' => [['docid' => 18910, 'date' => '2024-03-01', 'answer' => 'accepted']]])]
    private ?array $reviews = null;
    #[groups(['read:Statistic'])]
    #[Context(normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    #[ApiProperty(description: 'With `withPapers`: the number of reviews not listed in `reviews` because of a conflict of interest on their paper', schema: ['type' => 'integer', 'example' => 0])]
    private ?int $hiddenReviews = null;

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

    public function getPapers(): ?array
    {
        return $this->papers;
    }

    public function setPapers(?array $papers): self
    {
        $this->papers = $papers;
        return $this;
    }

    public function getReviews(): ?array
    {
        return $this->reviews;
    }

    public function setReviews(?array $reviews): self
    {
        $this->reviews = $reviews;
        return $this;
    }

    public function getHiddenReviews(): ?int
    {
        return $this->hiddenReviews;
    }

    public function setHiddenReviews(?int $hiddenReviews): self
    {
        $this->hiddenReviews = $hiddenReviews;
        return $this;
    }

    public function getHiddenPapers(): int|array|null
    {
        return $this->hiddenPapers;
    }

    public function setHiddenPapers(int|array|null $hiddenPapers): self
    {
        $this->hiddenPapers = $hiddenPapers;
        return $this;
    }

    public function setCount(?int $count): self
    {
        $this->count = $count;
        return $this;
    }


}
