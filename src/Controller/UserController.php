<?php

namespace Imanaging\CoreApplicationBundle\Controller;

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

    return new Response($this->twig->render("@ImanagingCoreApplication/User/index.html.twig", [
      'utilisateurs' => $this->em->getRepository(UserInterface::class)->findAll(),
      'basePath' => $this->coreApplication->getBasePath()
    ]));
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