<?php

namespace Imanaging\CoreApplicationBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;

final class CoreUserProvisioner
{
  public function __construct(private readonly EntityManagerInterface $em, private readonly CoreUserLock $lock) {}

  public function provision(array $data): array
  {
    $login = $this->validateLogin($data['login'] ?? null);
    if (!isset($data['active']) || !is_bool($data['active']) || !array_key_exists('role_id', $data)) {
      throw new UserApiException('Un état actif booléen et un rôle sont requis.', 422);
    }
    foreach (['nom', 'prenom', 'email'] as $field) {
      if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '' || mb_strlen($data[$field]) > 255) {
        throw new UserApiException('Les informations d’identité sont invalides.', 422);
      }
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)
      || ($data['active'] && (!is_string($data['role_id']) || trim($data['role_id']) === ''))
      || (!$data['active'] && $data['role_id'] !== null)) {
      throw new UserApiException('L’email ou le rôle est invalide.', 422);
    }
    return $this->lock->run(function () use ($data, $login) {
      // Lock acquisition precedes lookup, including the absent-user case.
      return $this->em->wrapInTransaction(function () use ($data, $login) {
        $user = $this->find($login);
        if ($user && (!$user->isUtilisateurCore() || $user->isUtilisateurZeus())) {
          throw new UserApiException('Un compte local ou Zeus existe déjà pour ce login.', 409);
        }
        if (!$user && !$data['active']) {
          return ['http_code' => 200, 'data' => $this->snapshot(null)];
        }
        $role = null;
        if ($data['active']) {
          $role = $this->em->getRepository(RoleInterface::class)->find($data['role_id']);
          if (!$role instanceof RoleInterface || $role->isZeusOnly()) {
            throw new UserApiException('Le rôle demandé est absent ou réservé à Zeus.', 422);
          }
        }
        $created = !$user;
        if ($created) {
          $class = $this->em->getClassMetadata(UserInterface::class)->getName();
          $user = new $class();
          if (!$user instanceof UserInterface) {
            throw new UserApiException('L’entité utilisateur du satellite est incompatible.', 503);
          }
          $user->setLogin($login);
        }
        $user->setUsername($login);
        $user->setNom(trim($data['nom'])); $user->setPrenom(trim($data['prenom']));
        $user->setMail($data['email']); $user->setRole($role); $user->setActif($data['active']);
        $user->setUtilisateurCore(true); $user->setUtilisateurZeus(false);
        // No password accessor, setter or hasher: authentication remains on the Core.
        $this->em->persist($user);
        $this->em->flush();
        return ['http_code' => $created ? 201 : 200, 'data' => $this->snapshot($user)];
      });
    });
  }

  public function check(mixed $login): array
  {
    return $this->snapshot($this->find($this->validateLogin($login)));
  }

  public function validateLogin(mixed $login): string
  {
    if (!is_string($login) || trim($login) === '' || $login !== trim($login) || mb_strlen($login) > 255) {
      throw new UserApiException('Le login est invalide.', 422);
    }
    return $login;
  }

  private function find(string $login): ?UserInterface
  {
    $users = $this->em->getRepository(UserInterface::class)->findBy(['login' => $login], null, 2);
    if (count($users) > 1 || (isset($users[0]) && $users[0]->getLogin() !== $login)) {
      throw new UserApiException('Plusieurs comptes ou un login différent correspondent à cette demande.', 409);
    }
    if (!$users) { return null; }
    // Force a fresh database read; no update, token generation or synchronisation is performed.
    $this->em->refresh($users[0]);
    return $users[0];
  }

  private function snapshot(?UserInterface $user): array
  {
    return ['success' => true, 'exists' => $user !== null, 'user' => $user ? [
      'login' => $user->getLogin(), 'role_id' => $user->getRole() ? (string)$user->getRole()->getId() : null,
      'active' => $user->isActif(), 'utilisateur_core' => $user->isUtilisateurCore(),
      'utilisateur_zeus' => $user->isUtilisateurZeus(),
    ] : null];
  }
}
