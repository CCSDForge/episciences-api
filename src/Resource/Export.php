<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Controller\ExportController;
use App\OpenApi\OpenApiFactory;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/papers/export/{docid}/{format}',
            //formats: [],
            controller: ExportController::class,
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['paper']],
                summary: 'Export',
                description: 'Export a document in a given format (exp. bibtex)',
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
                        name: "docid",
                        in: "path",
                        description: "Paper identifier",
                        required: true,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            "type" => "string",
                        ],
                        style: "simple",
                        explode: false,
                        allowReserved: false
                    ),

                    new Parameter(
                        name: 'format',
                        in: 'path',
                        description: 'Export format (exp. tei)',
                        required: true,
                        deprecated: false,
                        allowEmptyValue: false,
                        schema: [
                            "type" => 'string',
                            "default" => 'csl'
                        ],
                        style: "simple",
                        explode: false,
                        allowReserved: false
                    ),

                ],
            ),
            normalizationContext: [
                'groups' => ['read:Browse:Export']
            ],
            read: false,

        )

    ],
)]
class Export extends AbstractBrowse
{


    private int $docId;
    private ?string $format;

    public function getDocId(): int
    {
        return $this->docId;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function setDocId(int $docId): self
    {
        $this->docId = $docId;
        return $this;
    }

}