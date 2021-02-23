<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Controller\ImanagingController;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Doctrine\ORM\EntityManagerInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class MenuController extends ImanagingController
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

  public function getFirstSousModuleRedirectAction($id)
  {
    $user = $this->getUser();
    if ($user instanceof UserInterface){
      $role = $user->getRole();
      if ($role instanceof RoleInterface){
        $module = $this->em->getRepository(ModuleInterface::class)->find($id);
        if ($module instanceof ModuleInterface){
          $res = $this->recursiveFindFirstSousModule($role, $module);
          if (!is_null($res)){
            return $res;
          }
        }
      }
    }
    return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
  }

  private function recursiveFindFirstSousModule(RoleInterface $role, ModuleInterface $module){
    foreach ($module->getEnfants() as $sousModule){
      if ($sousModule instanceof ModuleInterface){
        if ($role->canAccess($sousModule->getId())){
          if ($sousModule->getRoute() != ''){
            try {
              // VRAI MODULE
              $this->generateUrl($sousModule->getRoute());
              return $this->redirectToRoute($sousModule->getRoute());
            } catch (RouteNotFoundException $e) {
              return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
            }
          } else {
            if (count($sousModule->getEnfants()) > 0){
              return $this->recursiveFindFirstSousModule($role, $sousModule);
            }
          }
        }
      }
    }
    return null;
  }
}