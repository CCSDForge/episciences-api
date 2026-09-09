<?php

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Review;
use App\Entity\User;
use App\EventSubscriber\JWTSubscriber;
use App\Repository\ReviewRepository;
use Doctrine\Persistence\ManagerRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class JWTSubscriberTest extends TestCase
{
    private RequestStack&MockObject $requestStack;
    private ManagerRegistry&MockObject $doctrine;
    private JWTSubscriber $subscriber;
    private ReviewRepository&MockObject $reviewRepo;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->doctrine     = $this->createMock(ManagerRegistry::class);
        $this->reviewRepo   = $this->createMock(ReviewRepository::class);

        $this->doctrine
            ->method('getRepository')
            ->with(Review::class)
            ->willReturn($this->reviewRepo);

        $this->subscriber = new JWTSubscriber($this->requestStack, $this->doctrine);
    }

    public static function getSubscribedEvents(): void
    {
        // static check — tested via the static method directly
    }

    public function testGetSubscribedEventsReturnsCorrectMapping(): void
    {
        $events = JWTSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey('lexik_jwt_authentication.on_jwt_created', $events);
        $this->assertSame('onLexikJwtAuthenticationOnJwtCreated', $events['lexik_jwt_authentication.on_jwt_created']);
    }

    // ------------------------------------------------------------------ helpers

    private function makeUser(int $uid = 1, ?int $rvId = null): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('getCurrentJournalID')->willReturn($rvId);
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        return $user;
    }

    private function makeEvent(User&MockObject $user, array $data = []): JWTCreatedEvent&MockObject
    {
        $event = $this->createMock(JWTCreatedEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getData')->willReturn($data);
        return $event;
    }

    // ------------------------------------------------------------------ no request (CLI / test context)

    public function testNoRequestAddsUidRolesRvId(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $user  = $this->makeUser(42, 7);
        $event = $this->makeEvent($user, ['sub' => 'user@example.com']);

        $event->expects($this->once())
            ->method('setData')
            ->with($this->callback(static fn(array $d) => $d['uid'] === 42 && $d['rvId'] === 7 && isset($d['roles'])));

        $this->subscriber->onLexikJwtAuthenticationOnJwtCreated($event);
    }

    // ------------------------------------------------------------------ request with empty body (regression fix)

    public function testEmptyRequestBodyDoesNotThrowJsonException(): void
    {
        $request = Request::create('/api/login', \Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], '');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $user  = $this->makeUser(1, null);
        $event = $this->makeEvent($user);
        $event->method('setData')->willReturn(null);

        // Must not throw JsonException
        $this->subscriber->onLexikJwtAuthenticationOnJwtCreated($event);
        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------ request with valid JSON but no 'code'

    public function testRequestWithoutCodeKeyUsesDefaultRvId(): void
    {
        $body    = json_encode(['username' => 'user@example.com', 'password' => 'secret'], JSON_THROW_ON_ERROR);
        $request = Request::create('/api/login', \Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], $body);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $user  = $this->makeUser(5, 3);
        $event = $this->makeEvent($user);

        $event->expects($this->once())
            ->method('setData')
            ->with($this->callback(static fn(array $d): bool => $d['rvId'] === 3 && $d['uid'] === 5));

        $this->subscriber->onLexikJwtAuthenticationOnJwtCreated($event);
    }

    // ------------------------------------------------------------------ request with valid journal code

    public function testValidJournalCodeSetsRvId(): void
    {
        $body    = json_encode(['code' => 'myjournal'], JSON_THROW_ON_ERROR);
        $request = Request::create('/api/login', \Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], $body);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $review = $this->createMock(Review::class);
        $review->method('getStatus')->willReturn(1);
        $review->method('getRvid')->willReturn(99);

        $this->reviewRepo->method('getJournalByIdentifier')->with('myjournal')->willReturn($review);

        $user = $this->makeUser(5, null);
        $user->expects($this->once())->method('setCurrentJournalID')->with(99);

        $event = $this->makeEvent($user);
        $event->expects($this->once())
            ->method('setData')
            ->with($this->callback(static fn(array $d): bool => $d['rvId'] === 99));

        $this->subscriber->onLexikJwtAuthenticationOnJwtCreated($event);
    }

    // ------------------------------------------------------------------ unknown journal code → BadRequestHttpException

    public function testUnknownJournalCodeThrowsBadRequest(): void
    {
        $body    = json_encode(['code' => 'unknown'], JSON_THROW_ON_ERROR);
        $request = Request::create('/api/login', \Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], $body);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->reviewRepo->method('getJournalByIdentifier')->with('unknown')->willReturn(null);

        $user  = $this->makeUser(5, null);
        $event = $this->makeEvent($user);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('unknown');

        $this->subscriber->onLexikJwtAuthenticationOnJwtCreated($event);
    }

    // ------------------------------------------------------------------ inactive journal → BadRequestHttpException

    public function testInactiveJournalThrowsBadRequest(): void
    {
        $body    = json_encode(['code' => 'inactive'], JSON_THROW_ON_ERROR);
        $request = Request::create('/api/login', \Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], $body);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $review = $this->createMock(Review::class);
        $review->method('getStatus')->willReturn(0); // inactive (falsy int)
        $review->method('getRvid')->willReturn(5);

        $this->reviewRepo->method('getJournalByIdentifier')->with('inactive')->willReturn($review);

        $user  = $this->makeUser(5, null);
        $event = $this->makeEvent($user);

        $this->expectException(BadRequestHttpException::class);

        $this->subscriber->onLexikJwtAuthenticationOnJwtCreated($event);
    }
}