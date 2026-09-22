<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\AppConstants;
use App\Entity\Section;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Groups;

class SectionTest extends TestCase
{
    private function getCollectionOperation(): GetCollection
    {
        $attributes = (new \ReflectionClass(Section::class))->getAttributes(ApiResource::class);
        self::assertCount(1, $attributes);

        /** @var ApiResource $resource */
        $resource = $attributes[0]->newInstance();

        foreach ($resource->getOperations() ?? [] as $operation) {
            if ($operation instanceof GetCollection) {
                return $operation;
            }
        }

        self::fail('GetCollection operation not found on Section');
    }

    public function testCollectionIsOrderedByJournalThenPositionAsc(): void
    {
        self::assertSame(
            [
                'rvid' => AppConstants::ORDER_DESC,
                'position' => AppConstants::ORDER_ASC,
                'sid' => AppConstants::ORDER_ASC,
            ],
            $this->getCollectionOperation()->getOrder()
        );
    }

    public function testPositionIsExposedInItemAndCollectionGroups(): void
    {
        $attributes = (new \ReflectionProperty(Section::class, 'position'))->getAttributes(Groups::class);
        self::assertCount(1, $attributes);

        $groups = $attributes[0]->newInstance()->getGroups();
        $sectionGroups = AppConstants::APP_CONST['normalizationContext']['groups']['section'];

        self::assertContains($sectionGroups['item']['read'][0], $groups);
        self::assertContains($sectionGroups['collection']['read'][0], $groups);
    }
}
