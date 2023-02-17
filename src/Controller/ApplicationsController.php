<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Controller\ImanagingController;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Doctrine\ORM\EntityManagerInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class ApplicationsController extends ImanagingController
{
  private $coreApplication;
  private TokenStorageInterface $tokenStorage;
  private Environment $twig;

  /**
   * MappingController constructor.
   * @param CoreApplication $coreApplication
   * @param TokenStorageInterface $tokenStorage
   * @param Environment $twig
   */
  public function __construct(CoreApplication $coreApplication, TokenStorageInterface $tokenStorage, Environment $twig)
  {
    $this->coreApplication = $coreApplication;
    $this->tokenStorage = $tokenStorage;
    $this->twig = $twig;
  }

  public function showApplicationsListAction()
  {
    $user = $this->tokenStorage->getToken()->getUser();
    $typesApplication = $this->coreApplication->getApplicationsListing($user);
    return new Response($this->twig->render("@ImanagingCoreApplication/Applications/list.html.twig", [
      'user' => $user,
      'types_application' => $typesApplication
    ]));
  }
}