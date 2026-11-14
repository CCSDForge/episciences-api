<?php

declare(strict_types=1);

namespace App\Tests\Functional\Wiring;

use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Repository\ReviewRepository;
use App\Resource\Journal;
use App\Service\MetadataSources;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/journals/{code} through the whole HTTP kernel: the item is public and exposes
 * the same Journal resource shape as the collection (GET /api/journals/), sharing the
 * JournalMapper. Neither the settings nor the DOI settings of the journal are returned.
 *
 * Needs neither a database nor the real repository: the ReviewRepository service is replaced
 * by a stub producing real Review entities. A fresh kernel is booted for every test, which lets
 * each test provide its own stub without touching the doctrine manager registry.
 */
class JournalSettingsVisibilityTest extends KernelTestCase
{
    private const int RVID = 7;
    private const string HOST = 'https://api.example.org';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testTheItemIsPublicAndExposesTheJournalResource(): void
    {
        $this->stubRepository($this->journal());

        $response = $this->request('/api/journals/dev');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($payload);

        // Only its public identity (rvid, code) and the safe display fields are exposed on the item.
        // `number` is deliberately omitted on the item: it is a duplicate of `rvid` for a
        // single journal (the collection keeps it as its 1-based sequential counter).
        $this->assertArrayNotHasKey('number', $payload);
        $this->assertSame(self::RVID, $payload['rvid'] ?? null);
        $this->assertSame('dev', $payload['code'] ?? null);
        $this->assertSame('Development journal', $payload['title'] ?? null);
        $this->assertSame(sprintf('https://dev.%s', Journal::DOMAIN), $payload['address'] ?? null);
    }

    public function testTheItemNeverReturnsTheSettings(): void
    {
        $this->stubRepository($this->journal());

        $response = $this->request('/api/journals/dev');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('settings', $payload);
        $this->assertArrayNotHasKey('doiSettings', $payload);
        $this->assertArrayNotHasKey('journalSettings', $payload);
    }

    /**
     * Unknown and disabled journals are the repository's responsibility: the provider serves
     * the journal only when the repository hands one back, and yields 404 otherwise.
     */
    public function testAnUnknownJournalYieldsANotFound(): void
    {
        $this->stubRepository(null);

        $response = $this->request('/api/journals/does-not-exist');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function stubRepository(?Review $journal): void
    {
        $repository = $this->createStub(ReviewRepository::class);
        $repository->method('getJournalByIdentifier')->willReturn($journal);

        static::getContainer()->set(ReviewRepository::class, $repository);

        $metadataSources = $this->createStub(MetadataSources::class);
        $metadataSources->method('getLabel')->willReturnCallback(
            static fn(int $id): string => $id === 1 ? 'accepted-repo' : ''
        );
        static::getContainer()->set(MetadataSources::class, $metadataSources);
    }

    private function journal(): Review
    {
        $journal = new Review();
        new \ReflectionProperty(Review::class, 'rvid')->setValue($journal, self::RVID);
        $journal->setCode('dev');
        $journal->setName('Development journal');

        foreach (['displayEmptyVolumes' => '1', 'doiPrefix' => '10.0000', 'encapsulateEditors' => '0'] as $name => $value) {
            $setting = new ReviewSetting();
            $setting->setValue($value);
            new \ReflectionProperty(ReviewSetting::class, 'setting')->setValue($setting, $name);
            $journal->addSetting($setting);
        }

        return $journal;
    }

    private function request(string $uri): Response
    {
        $request = Request::create($uri, server: ['HTTP_ACCEPT' => '*/*', 'HTTPS' => 'on']);

        return self::$kernel->handle($request);
    }
}
