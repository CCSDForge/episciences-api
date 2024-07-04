<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\AppConstants;
use App\Entity\Paper;
use App\OpenApi\OpenApiFactory;
use App\State\StatisticStateProcessor;
use App\State\StatisticStateProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/statistics/nb-submissions',
            openapi: new OpenApiOperation(
                tags: ['Statistics | UX'],
                summary: "Total number of submissions",
                description: "",
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
            normalizationContext: [
                'groups' => [
                    'read:Statistic'
                ]

            ],
        ),

    ],
    provider: StatisticStateProvider::class,
    processor: StatisticStateProcessor::class

)]
class Statistic
{
    #[groups(['read:Statistic'])]
    private string $name;
    #[groups(['read:Statistic'])]
    private float $value;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): self
    {
        $this->value = $value;
        return $this;
    }


}