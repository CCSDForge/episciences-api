<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use App\Entity\JournalSettingNg;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Repository\JournalSettingNgRepository;
use App\Repository\ReviewRepository;
use App\State\JournalSettingNgProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class JournalSettingNgProviderTest extends TestCase
{
    private JournalSettingNgProvider $provider;
    private MockObject $entityManager;
    private MockObject $logger;
    private MockObject $reviewRepo;
    private MockObject $settingRepo;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->reviewRepo = $this->createMock(ReviewRepository::class);
        $this->settingRepo = $this->createMock(JournalSettingNgRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturnMap([
                [Review::class, $this->reviewRepo],
                [JournalSettingNg::class, $this->settingRepo],
            ]);

        $this->provider = new JournalSettingNgProvider($this->entityManager, $this->logger);
    }

    public function testProvideReturnsNullWhenCodeIsAbsent(): void
    {
        $result = $this->provider->provide(new Get(), [], []);

        $this->assertNull($result);
    }

    public function testProvideReturnsNullWhenCodeIsEmptyString(): void
    {
        $result = $this->provider->provide(new Get(), [], ['filters' => ['code' => '']]);

        $this->assertNull($result);
    }

    public function testProvideThrowsResourceNotFoundWhenJournalNotFound(): void
    {
        $this->reviewRepo
            ->method('getJournalByIdentifier')
            ->with('unknown')
            ->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Oops! not found Journal unknown');

        $this->provider->provide(new Get(), [], ['filters' => ['code' => 'unknown']]);
    }

    public function testProvideThrowsResourceNotFoundWhenSettingsNotFound(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(5);

        $this->reviewRepo->method('getJournalByIdentifier')->willReturn($journal);
        $this->settingRepo->method('getFrontSettings')->with(5)->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('No configuration found for');

        $this->provider->provide(new Get(), [], ['filters' => ['code' => 'epijinfo']]);
    }

    public function testProvideReturnsResponseWithJsonSettings(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(12);

        $rawJson = '{"menu":{"authorsRender":true},"theme":{"primaryColor":"#49737e"}}';

        $this->reviewRepo->method('getJournalByIdentifier')->with('myjournal')->willReturn($journal);
        $this->settingRepo->method('getFrontSettings')->with(12)->willReturn($rawJson);

        $result = $this->provider->provide(new Get(), [], ['filters' => ['code' => 'myjournal']]);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(Response::HTTP_OK, $result->getStatusCode());
        $this->assertSame('application/json', $result->headers->get('Content-Type'));
        $this->assertSame($rawJson, $result->getContent());
    }

    public function testProvideLogsAndThrowsOnNonUniqueResultException(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(3);

        $this->reviewRepo->method('getJournalByIdentifier')->willReturn($journal);

        $exception = new NonUniqueResultException('multiple rows');
        $this->settingRepo->method('getFrontSettings')->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('critical')
            ->with('multiple rows');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Oops! An internal error has occurred. Please try again.');

        $this->provider->provide(new Get(), [], ['filters' => ['code' => 'epijinfo']]);
    }
}