<?php

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\EventSubscriber\OnSecurityAuthenticationSuccessSubscriber;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;

class OnSecurityAuthenticationSuccessSubscriberTest extends TestCase
{
    private OnSecurityAuthenticationSuccessSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new OnSecurityAuthenticationSuccessSubscriber();
    }

    public function testGetSubscribedEventsReturnsCorrectMapping(): void
    {
        $events = OnSecurityAuthenticationSuccessSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey('security.authentication.success', $events);
        $this->assertSame('onSecurityAuthenticationSuccess', $events['security.authentication.success']);
    }

    /**
     * When a PostRefreshTokenAuthenticationToken is present, the subscriber must
     * set the journal ID on the user from the refresh token.
     *
     * Note: the exact-class check (::class ===) means PHPUnit mocks (subclasses)
     * cannot exercise this branch in unit tests — an anonymous class extending the
     * real class is used instead.
     */
    public function testSetsCurrentJournalIdFromRefreshToken(): void
    {
        /** @var RefreshToken&MockObject $refreshToken */
        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('getRvId')->willReturn(42);

        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('setCurrentJournalID')->with(42);

        // Build a real PostRefreshTokenAuthenticationToken so ::class === check passes
        $token = new PostRefreshTokenAuthenticationToken($user, 'main', [], $refreshToken);
        $event = new AuthenticationSuccessEvent($token);

        $this->subscriber->onSecurityAuthenticationSuccess($event);
    }

    /**
     * With a different token type the subscriber must do nothing (no setCurrentJournalID call).
     */
    public function testDoesNothingForNonRefreshToken(): void
    {
        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->expects($this->never())->method('setCurrentJournalID');

        $token = new UsernamePasswordToken($user, 'main', []);
        $event = new AuthenticationSuccessEvent($token);

        $this->subscriber->onSecurityAuthenticationSuccess($event);
    }
}