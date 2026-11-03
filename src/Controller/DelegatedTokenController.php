<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\DelegatedTokenIssuer;
use App\Security\InvalidDelegationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Internal token exchange between Episciences and the API (see DelegatedTokenIssuer): not documented.
 */
final class DelegatedTokenController
{
    public const string PATH = '/api/token/delegated';

    #[Route(self::PATH, name: 'api_token_delegated', methods: ['POST'])]
    public function __invoke(Request $request, DelegatedTokenIssuer $issuer): JsonResponse
    {
        if (!$issuer->isEnabled()) {
            throw new NotFoundHttpException();
        }

        try {
            $content = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $content = null;
        }

        try {
            $token = $issuer->issue(is_array($content) ? $content : []);
        } catch (InvalidDelegationException) {
            // the same answer whatever the reason
            return new JsonResponse(['message' => 'Invalid delegation'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(['token' => $token]);
    }
}
