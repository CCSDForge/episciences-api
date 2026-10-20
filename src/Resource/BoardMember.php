<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\User;
use App\OpenApi\OpenApiSchemas;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Public view of a journal board member.
 * Not an API resource: no IRI, and no account identifiers (uid, uuid) or email address.
 */
final readonly class BoardMember
{
    /**
     * @param array<int, array<int, string>>    $roles
     * @param array<string, mixed>|null         $additionalProfileInformation
     * @param array<int, array<string, mixed>>|null $assignedSections
     */
    public function __construct(
        #[Groups(['read:Boards'])]
        #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'array', 'items' => ['type' => 'string']]])]
        public array $roles = [],
        #[Groups(['read:Boards'])]
        public string $langueid = 'fr',
        #[Groups(['read:Boards'])]
        public ?string $screenName = null,
        #[Groups(['read:Boards'])]
        public ?string $civ = null,
        #[Groups(['read:Boards'])]
        public ?string $lastname = null,
        #[Groups(['read:Boards'])]
        public ?string $firstname = null,
        #[Groups(['read:Boards'])]
        public ?string $middlename = null,
        #[Groups(['read:Boards'])]
        public ?string $orcid = null,
        #[Groups(['read:Boards'])]
        #[ApiProperty(schema: [
            'type' => ['object', 'array', 'null'],
            'maxItems' => 0,
            'properties' => [
                'affiliations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'rorId' => ['type' => 'string', 'format' => 'uri'],
                        ],
                    ],
                ],
                'webSites' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'format' => 'uri'],
                ],
            ],
        ])]
        public ?array $additionalProfileInformation = null,
        #[Groups(['read:Boards'])]
        #[ApiProperty(description: 'Profile picture as a data URI')]
        public ?string $picture = null,
        #[Groups(['read:Boards'])]
        #[ApiProperty(schema: [
            'type' => ['array', 'null'],
            'items' => [
                'type' => 'object',
                'properties' => [
                    'sid' => ['type' => 'integer'],
                    'titles' => OpenApiSchemas::NULLABLE_TRANSLATED_TEXT,
                ],
            ],
        ])]
        public ?array $assignedSections = null,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            roles: $user->getRoles(),
            langueid: $user->getLangueid() ?? 'fr',
            screenName: $user->getScreenName(),
            civ: $user->getCiv(),
            lastname: $user->getLastname(),
            firstname: $user->getFirstname(),
            middlename: $user->getMiddlename(),
            orcid: $user->getOrcid(),
            additionalProfileInformation: $user->getAdditionalProfileInformation(),
            picture: $user->getPicture(),
            assignedSections: $user->getAssignedSections(),
        );
    }
}
