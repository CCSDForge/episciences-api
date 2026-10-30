<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\ExportController;
use App\OpenApi\OpenApiFactory;
use App\Service\Export as ExportAlias;
use App\OpenApi\QueryParameters;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/papers/export/{docid}/{format}',
            // Default formats, plus the media types of the exported documents (see App\Service\Export::HEADERS_FORMATS)
            formats: ['jsonld', 'json', 'html', 'xml' => ['application/xml', 'text/xml'], 'text' => ['text/plain']],
            controller: ExportController::class,
            openapi: new OpenApiOperation(
                tags: [OpenApiFactory::OAF_TAGS['paper']],
                responses: [
                    '200' => new OpenApiResponse(
                        description: 'Exported document: JSON for the json and csl formats, plain text for bibtex, XML for the other formats',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                            'text/plain' => new MediaType(schema: new \ArrayObject(['type' => 'string'])),
                            'text/xml' => new MediaType(schema: new \ArrayObject(['type' => 'string'])),
                        ])
                    ),
                    '404' => new OpenApiResponse(description: 'Document not found, not yet published, or unknown format'),
                ],
                summary: 'Export',
                description: 'Export a document in a given format (exp. bibtex)',
                parameters: [
                    new Parameter(
                        name: 'code',
                        in: 'query',
                        description: QueryParameters::JOURNAL_CODE_DESCRIPTION,
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
                            "enum" => ExportAlias::AVAILABLE_FORMATS,
                            "default" => ExportAlias::JSON_FORMAT
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
    private ?string $format = null;

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