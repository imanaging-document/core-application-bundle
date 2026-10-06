<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use Imanaging\CoreApplicationBundle\Service\CoreUserProvisioner;
use Imanaging\CoreApplicationBundle\Service\UserApiException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class UserApiController
{
  public function __construct(private readonly CoreUserProvisioner $users, private readonly string $appSecret) {}

  public function provision(Request $request): JsonResponse
  {
    return $this->respond(function () use ($request) {
      $body = $this->authenticate($request);
      if (!isset($body['user']) || !is_array($body['user']) || array_is_list($body['user'])) {
        throw new UserApiException('Un objet utilisateur est requis.', 422);
      }
      $this->allowFields($body, ['token', 'user']);
      $this->allowFields($body['user'], ['login', 'nom', 'prenom', 'email', 'role_id', 'active']);
      return $this->users->provision($body['user']);
    });
  }

  public function check(Request $request): JsonResponse
  {
    return $this->respond(function () use ($request) {
      $body = $this->authenticate($request);
      $this->allowFields($body, ['token', 'login']);
      return ['http_code' => 200, 'data' => $this->users->check($body['login'] ?? null)];
    });
  }

  private function authenticate(Request $request): array
  {
    try { $body = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new UserApiException('Le corps JSON est invalide.', 400); }
    if (!is_array($body) || array_is_list($body)) { throw new UserApiException('Un objet JSON est requis.', 400); }
    if ($this->appSecret === '' || !is_string($body['token'] ?? null)
      || !hash_equals(hash('sha256', $this->appSecret), $body['token'])) {
      throw new UserApiException('Authentification refusée.', 403);
    }
    return $body;
  }

  private function allowFields(array $data, array $allowed): void
  {
    if (array_intersect(['password', 'password_hash'], array_keys($data))) {
      throw new UserApiException('Les mots de passe sont gérés exclusivement par le Core.', 422);
    }
    if (array_diff(array_keys($data), $allowed)) { throw new UserApiException('La requête contient des champs non autorisés.', 422); }
  }

  private function respond(callable $operation): JsonResponse
  {
    try {
      $result = $operation();
      return new JsonResponse($result['data'], $result['http_code'], ['Cache-Control' => 'no-store']);
    } catch (UserApiException $exception) {
      return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], $exception->httpStatus, ['Cache-Control' => 'no-store']);
    } catch (\Throwable) {
      // Do not expose or log the JSON payload, credentials, SQL or raw exception.
      return new JsonResponse(['success' => false, 'error' => 'Le service utilisateurs est indisponible.'], 503, ['Cache-Control' => 'no-store']);
    }
  }
}
