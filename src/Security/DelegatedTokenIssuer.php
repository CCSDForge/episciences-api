<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Delegated token: Episciences gets a short-lived JWT on behalf of the connected user, to display the papers
 * and the reviews of the statistics (withPapers) to the journal administrators. The request is signed with a secret shared by both applications;
 * the API keeps the control of the rights (roles of the journal) and of the conflicts of interest.
 * The token is only valid on the statistics (see DelegatedTokenScopeSubscriber).
 */
final readonly class DelegatedTokenIssuer
{
    public const string SCOPE = 'statistics-papers';
    /** Lifetime of a delegated token, in seconds */
    public const int TTL = 120;
    /** Maximum difference between the time of the request and the time of the server, in seconds */
    public const int MAX_CLOCK_SKEW = 60;
    public const int MIN_SECRET_LENGTH = 32;
    /** Routes a delegated token can be used on (GET only) */
    public const array ALLOWED_ROUTES = ['get_stats_dashboard_item', '_api_/statistics/_get_collection', '_api_/statistics/evaluation_get_collection'];

    private const string NONCE_CACHE_PREFIX = 'delegated_token_nonce_';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        #[Autowire('%app.delegation_secret%')]
        private string $secret,
    ) {
    }

    public function isEnabled(): bool
    {
        return strlen($this->secret) >= self::MIN_SECRET_LENGTH;
    }

    /**
     * @param array<string, mixed> $request uid, code (journal), iat (Unix time), nonce (32 hexadecimal characters),
     *                                      signature (hexadecimal HMAC-SHA256 of "uid|code|iat|nonce")
     * @throws InvalidDelegationException whatever the reason: the reason is only logged
     */
    public function issue(array $request, ?int $now = null): string
    {
        $now ??= time();

        if (!$this->isEnabled()) {
            $this->reject('disabled');
        }

        $uid = $request['uid'] ?? null;
        $code = $request['code'] ?? null;
        $iat = $request['iat'] ?? null;
        $nonce = $request['nonce'] ?? null;
        $signature = $request['signature'] ?? null;

        if (
            !(is_int($uid) || (is_string($uid) && ctype_digit($uid))) || (int)$uid < 1 ||
            !is_string($code) || $code === '' || strlen($code) > 50 ||
            !is_int($iat) ||
            !is_string($nonce) || preg_match('/^[0-9a-f]{32}$/', $nonce) !== 1 ||
            !is_string($signature) || preg_match('/^[0-9a-f]{64}$/', $signature) !== 1
        ) {
            $this->reject('invalid request');
        }

        $uid = (int)$uid;

        if (abs($now - $iat) > self::MAX_CLOCK_SKEW) {
            $this->reject('expired request', $uid, $code);
        }

        if (!hash_equals(hash_hmac('sha256', sprintf('%d|%s|%d|%s', $uid, $code, $iat, $nonce), $this->secret), $signature)) {
            $this->reject('invalid signature', $uid, $code);
        }

        // replay: a signed request can only be used once
        $nonceItem = $this->cache->getItem(self::NONCE_CACHE_PREFIX . $nonce);

        if ($nonceItem->isHit()) {
            $this->reject('replayed request', $uid, $code);
        }

        $this->cache->save($nonceItem->set(true)->expiresAfter(2 * self::MAX_CLOCK_SKEW));

        $user = $this->entityManager->getRepository(User::class)->find($uid);
        $journal = $this->entityManager->getRepository(Review::class)->getJournalByIdentifier($code); // active journals only

        if (!$user instanceof User || !$user->getIsValid() || !$journal instanceof Review) {
            $this->reject('unknown user or journal', $uid, $code);
        }

        // the roles of this journal are added by JWTSubscriber, as for a login
        $user->setCurrentJournalID($journal->getRvid());
        $token = $this->jwtManager->createFromPayload($user, ['exp' => $now + self::TTL, 'scope' => self::SCOPE]);

        $this->logger->info('Delegated token issued', ['uid' => $uid, 'code' => $code]);

        return $token;
    }

    /**
     * @throws InvalidDelegationException
     */
    private function reject(string $reason, ?int $uid = null, ?string $code = null): never
    {
        $this->logger->warning(sprintf('Delegated token refused: %s', $reason), ['uid' => $uid, 'code' => $code]);

        throw new InvalidDelegationException($reason);
    }
}
