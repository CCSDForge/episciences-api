<?php

declare(strict_types=1);

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use App\OpenApi\OpenApiSchemas;

#[ApiProperty(property: 'value', schema: [
    'type' => 'object',
    'properties' => [
        'nbImportedPublished' => ['type' => 'integer'],
        'nbSubmissions' => ['type' => 'integer'],
        'nbImported' => ['type' => 'integer'],
        'nbPublished' => ['type' => 'integer'],
        'nbRefused' => ['type' => 'integer'],
        'nbAccepted' => ['type' => 'integer'],
        'nbOtherStatus' => ['type' => 'integer'],
        'nbAcceptedNotYetPublished' => ['type' => 'integer'],
        'submissionAcceptanceTime' => OpenApiSchemas::STAT_DELAY,
        'submissionPublicationTime' => OpenApiSchemas::STAT_DELAY,
        'submissionAcceptanceTimeMedian' => OpenApiSchemas::STAT_DELAY,
        'submissionPublicationTimeMedian' => OpenApiSchemas::STAT_DELAY,
        'rates' => [
            'type' => 'object',
            'properties' => [
                'publication' => ['type' => ['number', 'null'], 'example' => 42.05],
                'acceptance' => ['type' => ['number', 'null'], 'example' => 43.72],
                'refusal' => ['type' => ['number', 'null'], 'example' => 56.28],
            ],
        ],
        'nbUsers' => ['type' => 'integer'],
    ],
])]
final class DashboardOutput extends AbstractStatResource
{}
