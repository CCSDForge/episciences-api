<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Review;
use App\Entity\User;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Security\DelegatedTokenIssuer;
use App\Security\InvalidDelegationException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class DelegatedTokenIssuerTest extends TestCase
{
    private const string SECRET = '0123456789abcdef0123456789abcdef';
    private const int NOW = 1_800_000_000;
    private const string NONCE = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    /**
     * A short-lived JWT, only valid on the statistics, for the requested journal (its roles are added by JWTSubscriber).
     */
    public function testTokenIsIssuedForTheUserAndTheJournal(): void
    {
        $user = new User()->setUid(7)->setIsValid(true);

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->once())
            ->method('createFromPayload')
            ->with(
                $this->callback(static fn(User $tokenUser): bool => $tokenUser === $user && $tokenUser->getCurrentJournalID() === 1),
                ['exp' => self::NOW + DelegatedTokenIssuer::TTL, 'scope' => DelegatedTokenIssuer::SCOPE]
            )
            ->willReturn('jwt');

        $this->assertSame('jwt', $this->issuer(user: $user, jwtManager: $jwtManager)->issue($this->request(), self::NOW));
    }

    public function testDisabledWithoutSecret(): void
    {
        $issuer = $this->issuer(secret: 'too short');

        $this->assertFalse($issuer->isEnabled());
        $this->expectException(InvalidDelegationException::class);

        $issuer->issue($this->request(), self::NOW);
    }

    #[DataProvider('refusedRequestProvider')]
    public function testRefusedRequest(array $changes, int $now = self::NOW): void
    {
        $this->expectException(InvalidDelegationException::class);

        $this->issuer()->issue(array_merge($this->request(), $changes), $now);
    }

    public static function refusedRequestProvider(): iterable
    {
        yield 'wrong signature' => [['signature' => str_repeat('0', 64)]];
        yield 'signature of another user' => [['uid' => 8]];
        yield 'signature of another journal' => [['code' => 'other']];
        yield 'too old' => [[], self::NOW + DelegatedTokenIssuer::MAX_CLOCK_SKEW + 1];
        yield 'in the future' => [[], self::NOW - DelegatedTokenIssuer::MAX_CLOCK_SKEW - 1];
        yield 'invalid uid' => [['uid' => 'abc']];
        yield 'invalid nonce' => [['nonce' => 'short']];
        yield 'iat sent as string' => [['iat' => (string)self::NOW]];
        yield 'missing signature' => [['signature' => null]];
    }

    /**
     * A signed request can only be used once.
     */
    public function testReplayedRequestIsRefused(): void
    {
        $issuer = $this->issuer();
        $issuer->issue($this->request(), self::NOW);

        $this->expectException(InvalidDelegationException::class);

        $issuer->issue($this->request(), self::NOW + 1);
    }

    #[DataProvider('unknownUserOrJournalProvider')]
    public function testUnknownUserOrJournalIsRefused(bool $userFound, bool $validUser, bool $journalFound): void
    {
        $this->expectException(InvalidDelegationException::class);

        $this->issuer(user: new User()->setUid(7)->setIsValid($validUser), userFound: $userFound, journalFound: $journalFound)->issue($this->request(), self::NOW);
    }

    public static function unknownUserOrJournalProvider(): iterable
    {
        yield 'unknown user' => [false, true, true];
        yield 'invalid user' => [true, false, true];
        yield 'unknown or disabled journal' => [true, true, false];
    }

    /**
     * @return array<string, mixed>
     */
    private function request(int $uid = 7, string $code = 'dmtcs', int $iat = self::NOW, string $nonce = self::NONCE): array
    {
        return [
            'uid' => $uid,
            'code' => $code,
            'iat' => $iat,
            'nonce' => $nonce,
            'signature' => hash_hmac('sha256', "$uid|$code|$iat|$nonce", self::SECRET),
        ];
    }

    private function issuer(string $secret = self::SECRET, ?User $user = null, bool $userFound = true, bool $journalFound = true, ?JWTTokenManagerInterface $jwtManager = null): DelegatedTokenIssuer
    {
        $user ??= new User()->setUid(7)->setIsValid(true);

        $journal = $this->createStub(Review::class);
        $journal->method('getRvid')->willReturn(1);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('find')->willReturn($userFound ? $user : null);

        $reviewRepository = $this->createStub(ReviewRepository::class);
        $reviewRepository->method('getJournalByIdentifier')->willReturn($journalFound ? $journal : null);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [User::class, $userRepository],
            [Review::class, $reviewRepository],
        ]);

        if ($jwtManager === null) {
            $jwtManager = $this->createStub(JWTTokenManagerInterface::class);
            $jwtManager->method('createFromPayload')->willReturn('jwt');
        }

        return new DelegatedTokenIssuer($entityManager, $jwtManager, $this->cache, new NullLogger(), $secret);
    }
}
