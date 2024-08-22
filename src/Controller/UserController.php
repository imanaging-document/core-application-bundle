<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use Imanaging\ApiCommunicationBundle\ApiCoreCommunication;
use Imanaging\CoreApplicationBundle\CoreApplication;
use Symfony\Component\HttpFoundation\Response;
use Imanaging\ZeusUserBundle\Controller\ImanagingController;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class UserController extends ImanagingController
{
  private $em;
  private $coreApplication;
  private $tokenStorage;
  private $twig;

  /**
   * MappingController constructor.
   * @param EntityManagerInterface $em
   * @param CoreApplication $coreApplication
   * @param TokenStorageInterface $tokenStorage
   * @param Environment $twig
   */
  public function __construct(EntityManagerInterface $em, CoreApplication $coreApplication, TokenStorageInterface $tokenStorage, Environment $twig)
  {
    $this->em = $em;
    $this->coreApplication = $coreApplication;
    $this->tokenStorage = $tokenStorage;
    $this->twig = $twig;
  }

  public function indexAction()
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_user'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $res = $this->coreApplication->getUsersInfos();
    $usersNotifications = [];
    $dernieresConnexions = [];
    if ($res['success']){
      $usersNotifications = $res['notifications'];
      $dernieresConnexions = $res['dernieres_connexions'];
    }

    return new Response($this->twig->render("@ImanagingCoreApplication/User/index.html.twig", [
      'utilisateurs' => $this->em->getRepository(UserInterface::class)->findBy(['utilisateurCore' => true]),
      'utilisateurs_notifications' => $usersNotifications,
      'dernieres_connexions' => $dernieresConnexions,
      'basePath' => $this->coreApplication->getBasePath()
    ]));
  }

  public function addPageAction()
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_user'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    return new Response($this->twig->render("@ImanagingCoreApplication/User/add.html.twig", [
      'roles' => $this->em->getRepository(RoleInterface::class)->findBy(['zeusOnly' => false]),
      'basePath' => $this->coreApplication->getBasePath()
    ]));
  }

  public function addAction(Request $request)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_user'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();
    $res = $this->coreApplication->addUser($params['login'], $params['mail'], $params['nom'], $params['prenom'], $params['password'], true, $params['role']);
    if ($res['added']){
      $this->addFlash('success', 'Nouvel utilisateur ajouté avec succès : '.$params['login']);
      return $this->redirectToRoute('core_application_user');
    } else {
      $this->addFlash('error', 'L\'ajout a échoué : '.$res['error_message']);
      return $this->redirectToRoute('core_application_user_add_page');
    }
  }

  public function editAction($id)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_user'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $user = $this->em->getRepository(UserInterface::class)->find($id);
    if ($user instanceof UserInterface){
      return new Response($this->twig->render("@ImanagingCoreApplication/User/edit.html.twig", [
        'utilisateur' => $user,
        'roles' => $this->em->getRepository(RoleInterface::class)->findBy(['zeusOnly' => false]),
        'notifications' => $this->coreApplication->getUserNotifications($user->getLogin()),
        'basePath' => $this->coreApplication->getBasePath()
      ]));
    } else {
      $this->addFlash('error', 'Utilisateur introuvable : '.$id);
      return $this->redirectToRoute('core_application_user');
    }
  }

  public function saveAction($id, Request $request)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_user'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $user = $this->em->getRepository(UserInterface::class)->find($id);
    if ($user instanceof UserInterface){
      $params = $request->request->all();
      $res = $this->coreApplication->editUser($user->getLogin(), $params['mail'], $params['nom'], $params['prenom'], $params['role']);
      if ($res['updated']){
        $this->addFlash('success', 'Utilisateur enregistré avec succès : '.$user->getLogin());
        return $this->redirectToRoute('core_application_user');
      } else {
        $this->addFlash('error', 'L\'ajout a échoué : '.$res['error_message']);
        return $this->redirectToRoute('core_application_user_add_page');
      }
    } else {
      $this->addFlash('error', 'Utilisateur introuvable : '.$id);
    }
    return $this->redirectToRoute('core_application_user');
  }

  public function disableAction($id)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_user'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $user = $this->em->getRepository(UserInterface::class)->find($id);
    if ($user instanceof UserInterface){
      $res = $this->coreApplication->disableUser($user);
      if ($res['success']){
        $this->addFlash('success', 'L\'utilisateur a été désactivé avec succes : '.$user->getLogin());
      } else {
        $this->addFlash('error', 'La désactivation a échoué : '.$res['error_message']);
      }
    } else {
      $this->addFlash('error', 'Utilisateur introuvable : '.$id);
    }
    return $this->redirectToRoute('core_application_user');
  }

  public function sendNotificationAction($id)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_user'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $user = $this->em->getRepository(UserInterface::class)->find($id);
    if ($user instanceof UserInterface){
      $res = $this->coreApplication->sendNotification($user);
      if ($res['success']){
        $this->addFlash('success', 'Notification envoyée avec succes pour : '.$user->getLogin());
      } else {
        $this->addFlash('error', 'L\'envoi de la notification a échoué : '.$res['error_message']);
      }
    } else {
      $this->addFlash('error', 'Utilisateur introuvable : '.$id);
    }
    return $this->redirectToRoute('core_application_user_edit', ['id' => $id]);
  }

  public function synchroniserAction()
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_user'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $res = $this->coreApplication->synchroniserUsers();
    if ($res['success']){
      return new JsonResponse();
    } else {
      return new JsonResponse(['error_message' => $res['error_message']], 500);
    }
  }
}