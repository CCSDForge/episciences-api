<?php

namespace App\Security;

use App\Entity\Main\User;
use App\Repository\Main\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class TokenAuthenticator extends AbstractAuthenticator
{
    private EntityManagerInterface $em;
    private ParameterBagInterface $parameterBag;
    private RoleHierarchyInterface $roleHierarchy;
    private ?int $rvId;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $parameterBag, RoleHierarchyInterface $roleHierarchy)
    {
        $this->em = $em;
        $this->parameterBag = $parameterBag;
        $this->roleHierarchy = $roleHierarchy;
        $this->rvId = null;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     * @param Request $request
     * @return bool
     */
    public function supports(Request $request): bool
    {

        $rvId = ($request->headers->has('X-AUTH-RVID') && !empty($request->headers->get('X-AUTH-RVID'))) ?
            $request->headers->get('X-AUTH-RVID') : $request->get('rvid');

        if (!empty($rvId)) {
            $this->rvId = (int)$rvId;
        }

        if (
            !$request->headers->has('X-AUTH-TOKEN') ||
            ($request->headers->get('X-AUTH-TOKEN') !== $this->parameterBag->get('episciences.auth.token'))
        ) {
            return true; // authentication required
        }

        // false : authentication not required
        return
            $request->headers->has('X-AUTH-LOGIN') && !empty($request->headers->get('X-AUTH-LOGIN'));

    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     * @param Request $request
     * @return string|null
     */
    public function getCredentials(Request $request): ?string
    {
        return $request->headers->get('X-AUTH-LOGIN') ?: false;
    }

    public function getUser($credentials, UserProviderInterface $userProvider): ?UserInterface
    {

        if (empty($credentials)) {
            // The credentials are empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            return null;
        }

        /** @var User $user */
        return $this->em->getRepository(User::class)->findOneBy(['username' => $credentials]);
    }

    public function checkCredentials($credentials, UserInterface $user): bool

    {
        /** @var User $user */
        return $this->isGranted($user, $this->rvId);
    }

    /**
     * @param Request $request
     * @param TokenInterface $token
     * @param string $providerKey
     * @return Response|null
     */

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        $data = [ // you may want to customize or obfuscate the message first
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())
            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Called when authentication is needed, but it's not sent
     * @param Request $request
     * @param AuthenticationException|null $authException
     * @return JsonResponse
     */
    public function start(Request $request, AuthenticationException $authException = null): JsonResponse
    {
        $data = [ // you might translate this message
            'message' => 'Authentication Required'
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }

    /**
     * @param User $user
     * @param int|null $rvId
     * @return bool
     */
    private function isGranted(User $user, ?int $rvId): bool
    {
        if (!$rvId) {
            return false;
        }

        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles($rvId));

        if (in_array('ROLE_SECRETARY', $reachableRoles, true)) {
            return true;
        }

        return false;
    }

    public function authenticate(Request $request): Passport
    {

        $login = (string)$request->headers->get('X-AUTH-LOGIN');

        return new SelfValidatingPassport(
            new UserBadge($login, function (string $login) {
                return $this->em->getRepository(User::class)->findOneBy(['username' => $login]);
            })
        );
    }
}