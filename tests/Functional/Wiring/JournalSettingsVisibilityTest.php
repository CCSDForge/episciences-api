<?php

declare(strict_types=1);

namespace App\Tests\Functional\Wiring;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Entity\User;
use App\OpenApi\OpenApiFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The settings of a journal are only returned by GET /api/journals/{code}, as on the settings pages of Episciences:
 * the settings of the journal to its chief editors, administrators and editorial secretaries (JWT of this journal)
 * and to the platform administrators, the DOI settings to the platform administrators only.
 *
 * Serializes an in-memory journal with the context of the operations, through the API Platform normalizers
 * (property security included). Needs neither a database nor Solr.
 */
class JournalSettingsVisibilityTest extends KernelTestCase
{
    private const int RVID = 7;

    /**
     * @return iterable<string, array{list<string>|null, int|null, bool, bool}>
     */
    public static function viewers(): iterable
    {
        yield 'anonymous' => [null, null, false, false];
        yield 'member of the journal' => [['ROLE_MEMBER'], self::RVID, false, false];
        yield 'editor of the journal' => [['ROLE_EDITOR'], self::RVID, false, false];
        yield 'secretary of the journal' => [['ROLE_SECRETARY'], self::RVID, true, false];
        yield 'administrator of the journal' => [['ROLE_ADMINISTRATOR'], self::RVID, true, false];
        yield 'chief editor of the journal' => [['ROLE_CHIEF_EDITOR'], self::RVID, true, false];
        yield 'secretary of another journal' => [['ROLE_SECRETARY'], self::RVID + 1, false, false];
        yield 'platform administrator' => [['ROLE_EPIADMIN'], self::RVID + 1, true, true];
    }

    /**
     * @param list<string>|null $roles null: anonymous
     */
    #[DataProvider('viewers')]
    public function testTheItemReturnsTheSettingsAsEpisciences(?array $roles, ?int $tokenJournal, bool $seesSettings, bool $seesDoiSettings): void
    {
        $data = $this->normalize(Get::class, $roles, $tokenJournal);

        if ($seesSettings) {
            $this->assertSame([['setting' => 'displayEmptyVolumes', 'value' => '1'], ['setting' => 'encapsulateEditors', 'value' => '0']], $data['settings']);
        } else {
            $this->assertArrayNotHasKey('settings', $data);
        }

        if ($seesDoiSettings) {
            $this->assertSame([['setting' => 'doiPrefix', 'value' => '10.0000']], $data['doiSettings']);
        } else {
            $this->assertArrayNotHasKey('doiSettings', $data);
        }

        $this->assertArrayNotHasKey('journalSettings', $data);
    }

    public function testTheCollectionNeverReturnsTheSettings(): void
    {
        $data = $this->normalize(\ApiPlatform\Metadata\GetCollection::class, ['ROLE_EPIADMIN'], self::RVID);

        $this->assertArrayNotHasKey('settings', $data);
        $this->assertArrayNotHasKey('doiSettings', $data);
        $this->assertArrayNotHasKey('journalSettings', $data);
    }

    /**
     * The JWT is optional on the item: without the bearer requirement, Swagger UI would never send the token and the
     * settings would never be returned from the documentation.
     */
    public function testTheItemIsDocumentedWithAnOptionalJwt(): void
    {
        self::bootKernel();

        /** @var OpenApiFactoryInterface $factory */
        $factory = static::getContainer()->get(OpenApiFactoryInterface::class);
        $get = $factory()->getPaths()->getPath('/api/journals/{code}')?->getGet();

        $this->assertNotNull($get);
        $this->assertEquals([new \ArrayObject(), [OpenApiFactory::BEARER_AUTH => []]], $get->getSecurity());
        $this->assertArrayHasKey(401, $get->getResponses() ?? []);
    }

    /**
     * @param class-string $operationClass
     * @param list<string>|null $roles
     * @return array<string, mixed>
     */
    private function normalize(string $operationClass, ?array $roles, ?int $tokenJournal): array
    {
        self::bootKernel();
        $container = static::getContainer();

        if ($roles !== null) {
            $user = new User();
            $user->setUid(42);
            $user->setCurrentJournalID($tokenJournal);
            $container->get(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($user, 'api', $roles));
        }

        $operation = null;

        foreach ($container->get(ResourceMetadataCollectionFactoryInterface::class)->create(Review::class) as $resource) {
            foreach ($resource->getOperations() as $candidate) {
                if ($candidate::class === $operationClass && in_array($candidate->getUriTemplate(), [Review::URI_TEMPLATE . '{code}', Review::URI_TEMPLATE], true)) {
                    $operation = $candidate;
                }
            }
        }

        $this->assertNotNull($operation);

        /** @var NormalizerInterface $serializer */
        $serializer = $container->get('serializer');

        $data = $serializer->normalize($this->journal(), 'jsonld', $operation->getNormalizationContext() + [
            'operation' => $operation,
            'resource_class' => Review::class,
        ]);

        $this->assertIsArray($data);

        return $data;
    }

    private function journal(): Review
    {
        $journal = new Review();
        $this->set($journal, 'rvid', self::RVID);
        $journal->setCode('dev');
        $journal->setName('Development journal');

        foreach (['displayEmptyVolumes' => '1', 'doiPrefix' => '10.0000', 'encapsulateEditors' => '0'] as $name => $value) {
            $setting = new ReviewSetting();
            $this->set($setting, 'rvid', self::RVID);
            $this->set($setting, 'setting', $name);
            $setting->setValue($value);
            $journal->addSetting($setting);
        }

        return $journal;
    }

    private function set(object $object, string $property, mixed $value): void
    {
        new \ReflectionProperty($object, $property)->setValue($object, $value);
    }
}
