<?php

declare(strict_types=1);

namespace App\Tests\Functional\Provider;

use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Repository\ReviewRepository;
use App\Service\MetadataSources;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/journals/ through the whole HTTP kernel: public access, paginated JSON and the
 * explicit `format` parameter (including the CSV download), restricted to the active journals.
 *
 * Needs neither a database nor the real repository: the ReviewRepository service is replaced
 * by a stub producing real Review entities. A fresh kernel is booted for every test, which lets
 * each test provide its own stub without touching the doctrine manager registry.
 */
class JournalCollectionProviderTest extends KernelTestCase
{
    private const string HOST = 'https://api.example.org';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    /**
     * @return array<string, array{string, string}> the new query string for `format` and the expected media type
     */
    public static function formatProvider(): array
    {
        return [
            'csv' => ['?format=csv', 'text/csv'],
            'json' => ['?format=json', 'application/json'],
            'jsonld' => ['?format=jsonld', 'application/ld+json'],
        ];
    }

    #[DataProvider('formatProvider')]
    public function testJournalsAreServedInTheRequestedFormat(string $query, string $contentType): void
    {
        $this->stubRepository($this->activeJournal());

        $response = $this->request('/api/journals/' . $query);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringStartsWith($contentType, (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('active', $this->content($response));
    }

    public function testJournalCollectionIsPublicWithoutAuthentication(): void
    {
        $this->stubRepository($this->activeJournal());

        $response = $this->request('/api/journals/');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * The active-journal filtering is the repository's responsibility (see
     * ReviewRepository::getActiveJournals()). The provider must serve exactly what the
     * repository hands back, without adding or removing journals on its own.
     */
    public function testProviderServesExactlyWhatTheRepositoryReturns(): void
    {
        $this->stubRepository($this->disabledJournal());

        $response = $this->request('/api/journals/');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(1, $this->payloadTotalItems($response));
    }

    public function testCsvIsAStreamedDownloadWithTheExpectedHeaders(): void
    {
        $this->stubRepository($this->activeJournal());

        $response = $this->request('/api/journals/?format=csv');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertSame(
            'attachment; filename="journals_' . date('Ymd') . '.csv"',
            (string) $response->headers->get('Content-Disposition')
        );
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertSame('no-cache', (string) $response->headers->get('Pragma'));
        $this->assertSame('0', (string) $response->headers->get('Expires'));

        $csv = $this->content($response);

        // A first column-name header row identifies each column, the subtitle
        // being one of the legacy fields it exposes.
        $lines = explode(PHP_EOL, trim($csv));
        $this->assertSame(
            ['number', 'code', 'title', 'subtitle', 'issn', 'eissn', 'address', 'acceptedRepositories'],
            str_getcsv($lines[0], ',', '"', '\\')
        );

        $this->assertStringContainsString('active', $csv);
        $this->assertStringContainsString('1234-5678', $csv);
        $this->assertStringContainsString('accepted-repo', $csv);
        $this->assertStringContainsString('Informatics', $csv);

        // Each journal's address is derived from its code and the shared domain.
        $this->assertStringContainsString('https://active.episciences.org', $csv);
    }

    public function testJsonIsPaginated(): void
    {
        $this->stubRepository($this->activeJournal(), $this->activeJournal());

        $response = $this->request('/api/journals/?format=json&itemsPerPage=1');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertCount(1, $payload);
        $this->assertSame('active', $payload[0]['code'] ?? null);
    }

    private function activeJournal(): Review
    {
        return $this->buildReview('active', [
            ReviewSetting::SETTING_ISSN_PRINT => '12345678',
            ReviewSetting::SETTING_ISSN => '87654321',
            ReviewSetting::SETTING_REPOSITORIES => '[1]',
        ])->setSubtitle('Informatics');
    }

    private function disabledJournal(): Review
    {
        return $this->buildReview('disabled', [], Review::STATUS_DISABLED);
    }

    /**
     * Builds a real Review entity, its settings stored as real ReviewSetting objects
     * (the `setting` key being set through reflection, as there is no public setter).
     *
     * @param array<string, string> $settings
     */
    private function buildReview(string $name, array $settings, int $status = Review::STATUS_ENABLED): Review
    {
        $review = (new Review())
            ->setCode($name)
            ->setName($name)
            ->setStatus($status);

        foreach ($settings as $key => $value) {
            $setting = new ReviewSetting();
            $setting->setValue($value);

            $property = new \ReflectionProperty(ReviewSetting::class, 'setting');
            $property->setValue($setting, $key);

            $review->addSetting($setting);
        }

        return $review;
    }

    private function stubRepository(Review ...$journals): void
    {
        $repository = $this->createStub(ReviewRepository::class);
        $repository->method('getActiveJournals')->willReturn($journals);

        static::getContainer()->set(ReviewRepository::class, $repository);

        $metadataSources = $this->createStub(MetadataSources::class);
        $metadataSources->method('getLabel')->willReturnCallback(
            static fn(int $id): string => $id === 1 ? 'accepted-repo' : ''
        );
        static::getContainer()->set(MetadataSources::class, $metadataSources);
    }

    private function request(string $uri): Response
    {
        $request = Request::create($uri, server: ['HTTP_ACCEPT' => '*/*', 'HTTPS' => 'on']);

        return self::$kernel->handle($request);
    }

    /**
     * The body of a response. A StreamedResponse never buffers its body on the object
     * (getContent() returns false and setContent(non-null) throws), so its body is captured
     * while sending it, under output buffering.
     */
    private function content(Response $response): string
    {
        if (!$response instanceof StreamedResponse) {
            return (string) $response->getContent();
        }

        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /**
     * The `hydra:totalItems` count of a JSON (LD) collection response.
     */
    private function payloadTotalItems(Response $response): int
    {
        $payload = json_decode((string) $response->getContent(), true);

        return is_array($payload) ? (int) ($payload['hydra:totalItems'] ?? 0) : 0;
    }
}
