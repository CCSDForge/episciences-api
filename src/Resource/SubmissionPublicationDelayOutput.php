<?php

declare(strict_types=1);

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use App\OpenApi\OpenApiSchemas;

#[ApiProperty(property: 'value', schema: OpenApiSchemas::STAT_DELAY)]
final class SubmissionPublicationDelayOutput extends AbstractStatResource
{}
