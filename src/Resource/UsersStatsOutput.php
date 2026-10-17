<?php

declare(strict_types=1);

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use App\OpenApi\OpenApiSchemas;

#[ApiProperty(property: 'value', schema: OpenApiSchemas::STAT_VALUE)]
final class UsersStatsOutput extends AbstractStatResource
{}
