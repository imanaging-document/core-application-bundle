<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Controller\ImanagingController;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UserController extends ImanagingController
{
  private $em;
  private $coreApplication;

  /**
   * MappingController constructor.
   * @param EntityManagerInterface $em
   * @param CoreApplication $coreApplication
   */
  public function __construct(EntityManagerInterface $em, CoreApplication $coreApplication)
  {
    $this->em = $em;
    $this->coreApplication = $coreApplication;
  }

  public function indexAction()
  {
    if (!$this->userCanAccess($this->getUser(), ['core_application_user'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }
    
    return $this->render("@ImanagingCoreApplication/User/index.html.twig", [
      'utilisateurs' => $this->em->getRepository(UserInterface::class)->findAll(),
      'basePath' => $this->coreApplication->getBasePath()
    ]);
  }

  public function synchroniserAction()
  {
    if (!$this->userCanAccess($this->getUser(), ['core_application_user'])){
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