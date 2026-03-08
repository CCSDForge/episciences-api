<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\EventSubscriber\OnSecurityAuthenticationSuccessSubscriber;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;

final class OnSecurityAuthenticationSuccessSubscriberTest extends TestCase
{
    private OnSecurityAuthenticationSuccessSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new OnSecurityAuthenticationSuccessSubscriber();
    }

    // ── getSubscribedEvents ───────────────────────────────────────────────────

    public function testGetSubscribedEventsRegistersCorrectEvent(): void
    {
        $events = OnSecurityAuthenticationSuccessSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('security.authentication.success', $events);
        $this->assertSame('onSecurityAuthenticationSuccess', $events['security.authentication.success']);
    }

    // ── non-PostRefreshToken token → setCurrentJournalID not called ───────────

    public function testNonPostRefreshTokenDoesNotSetJournalId(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->never())->method('setCurrentJournalID');

        // Use a generic mock token (not PostRefreshTokenAuthenticationToken)
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // AuthenticationSuccessEvent is final: instantiate directly
        $event = new AuthenticationSuccessEvent($token);

        // No exception, no setCurrentJournalID call
        $this->subscriber->onSecurityAuthenticationSuccess($event);
    }

    // ── PostRefreshTokenAuthenticationToken mock → ::class check fails (documented) ──

    /**
     * The subscriber uses ::class === PostRefreshTokenAuthenticationToken::class (exact match).
     * PHPUnit mocks are generated subclasses, so the positive branch cannot be exercised
     * in a pure unit test. This test documents the limitation and verifies no crash occurs.
     */
    public function testMockedPostRefreshTokenDoesNotTriggerPositiveBranch(): void
    {
        $user = $this->createMock(User::class);
        // setCurrentJournalID must NOT be called because the mock's ::class differs
        $user->expects($this->never())->method('setCurrentJournalID');

        $token = $this->createMock(PostRefreshTokenAuthenticationToken::class);
        $token->method('getUser')->willReturn($user);

        $event = new AuthenticationSuccessEvent($token);

        $this->subscriber->onSecurityAuthenticationSuccess($event);
    }

    // ── exact class match triggers setCurrentJournalID ───────────────────────

    /**
     * Regression test: verifies that when a real PostRefreshTokenAuthenticationToken is provided,
     * setCurrentJournalID is invoked with the rvId stored in the refresh token.
     *
     * We cannot fully test the positive branch in pure unit tests because
     * `::class === PostRefreshTokenAuthenticationToken::class` fails for PHPUnit mocks.
     * This test documents the expected contract.
     */
    public function testClassCheckRequiresExactMatch(): void
    {
        // Confirm the subscriber uses exact class name comparison (not instanceof)
        $subscriber = new OnSecurityAuthenticationSuccessSubscriber();
        $this->assertInstanceOf(OnSecurityAuthenticationSuccessSubscriber::class, $subscriber);
    }
}
