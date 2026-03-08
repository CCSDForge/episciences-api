<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Review;
use App\Entity\User;
use App\EventSubscriber\JWTSubscriber;
use App\Repository\ReviewRepository;
use Doctrine\Persistence\ManagerRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class JWTSubscriberTest extends TestCase
{
    private function makeSubscriber(
        ?Request $request,
        ?Review $journalResult = null,
        bool $noRepo = false
    ): JWTSubscriber {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $doctrine = $this->createMock(ManagerRegistry::class);

        if (!$noRepo) {
            $repo = $this->createMock(ReviewRepository::class);
            $repo->method('getJournalByIdentifier')->willReturn($journalResult);
            $doctrine->method('getRepository')->with(Review::class)->willReturn($repo);
        }

        return new JWTSubscriber($requestStack, $doctrine);
    }

    private function makeEvent(User $user, array $initialData = []): JWTCreatedEvent
    {
        $event = $this->createMock(JWTCreatedEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getData')->willReturn($initialData);
        return $event;
    }

    private function makeUser(int $uid = 42, int $rvId = 7, array $roles = ['ROLE_USER']): User
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('getCurrentJournalID')->willReturn($rvId);
        $user->method('getRoles')->willReturn($roles);
        return $user;
    }

    // ── getSubscribedEvents ──────────────────────────────────────────────────

    public function testGetSubscribedEvents(): void
    {
        $events = JWTSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('lexik_jwt_authentication.on_jwt_created', $events);
        $this->assertSame('onLexikJwtAuthenticationOnJwtCreated', $events['lexik_jwt_authentication.on_jwt_created']);
    }

    // ── basic payload enrichment ─────────────────────────────────────────────

    public function testPayloadContainsUidRolesRvId(): void
    {
        $user = $this->makeUser(42, 7, ['ROLE_USER']);
        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn('{}');

        $subscriber = $this->makeSubscriber($request, null, true);

        $capturedData = null;
        $event = $this->createMock(JWTCreatedEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getData')->willReturn([]);
        $event->expects($this->once())->method('setData')->willReturnCallback(
            static function (array $data) use (&$capturedData) {
                $capturedData = $data;
            }
        );

        $subscriber->onLexikJwtAuthenticationOnJwtCreated($event);

        $this->assertSame(42, $capturedData['uid']);
        $this->assertSame(['ROLE_USER'], $capturedData['roles']);
        $this->assertSame(7, $capturedData['rvId']);
    }

    // ── journal code lookup ──────────────────────────────────────────────────

    public function testWithValidJournalCode(): void
    {
        $user = $this->makeUser(42, 7);
        $user->expects($this->once())->method('setCurrentJournalID')->with(99);

        $review = $this->createMock(Review::class);
        $review->method('getStatus')->willReturn(1);
        $review->method('getRvid')->willReturn(99);

        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn('{"code":"epijinfo"}');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $repo = $this->createMock(ReviewRepository::class);
        $repo->method('getJournalByIdentifier')->with('epijinfo')->willReturn($review);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getRepository')->with(Review::class)->willReturn($repo);

        $subscriber = new JWTSubscriber($requestStack, $doctrine);

        $capturedData = null;
        $event = $this->createMock(JWTCreatedEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getData')->willReturn([]);
        $event->method('setData')->willReturnCallback(
            static function (array $data) use (&$capturedData) {
                $capturedData = $data;
            }
        );

        $subscriber->onLexikJwtAuthenticationOnJwtCreated($event);

        $this->assertSame(99, $capturedData['rvId']);
    }

    public function testWithInvalidJournalCodeThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/unknown/');

        $user = $this->makeUser();
        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn('{"code":"unknown"}');

        $subscriber = $this->makeSubscriber($request); // repo returns null

        $event = $this->makeEvent($user);
        $subscriber->onLexikJwtAuthenticationOnJwtCreated($event);
    }

    public function testWithInactiveJournalThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $user = $this->makeUser();
        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn('{"code":"inactive"}');

        $review = $this->createMock(Review::class);
        $review->method('getStatus')->willReturn(0); // inactive

        $subscriber = $this->makeSubscriber($request, $review);

        $event = $this->makeEvent($user);
        $subscriber->onLexikJwtAuthenticationOnJwtCreated($event);
    }

    // ── null request ─────────────────────────────────────────────────────────

    public function testNoRequestDoesNotCrash(): void
    {
        $user = $this->makeUser(42, 7, ['ROLE_USER']);

        $subscriber = $this->makeSubscriber(null, null, true);

        $capturedData = null;
        $event = $this->createMock(JWTCreatedEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getData')->willReturn([]);
        $event->method('setData')->willReturnCallback(
            static function (array $data) use (&$capturedData) {
                $capturedData = $data;
            }
        );

        $subscriber->onLexikJwtAuthenticationOnJwtCreated($event);

        $this->assertArrayHasKey('uid', $capturedData);
        $this->assertSame(42, $capturedData['uid']);
    }
}
