<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use Exception;
use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\NotificationInterface;
use Imanaging\ZeusUserBundle\Interfaces\FonctionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RoleController extends AbstractController
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

  public function indexAction(Request $request)
  {
    $params = $request->request->all();
    return $this->render("@ImanagingCoreApplication/Role/index.html.twig", [
      'roles' => $this->em->getRepository(RoleInterface::class)->findAll(),
      'basePath' => $this->coreApplication->getBasePath()
    ]);
  }

  public function addRoleAction(Request $request)
  {
    $params = $request->request->all();

    $roles = $this->em->getRepository(RoleInterface::class)->findAll();
    $maxRoleLocalId = 999;
    foreach ($roles as $role){
      if ($role instanceof RoleInterface){
        if ($role->getId() > $maxRoleLocalId){
          $maxRoleLocalId = $role->getId();
        }
      }
    }
    $maxRoleLocalId += 1;

    $className = $this->em->getRepository(RoleInterface::class)->getClassName();
    $role = new $className();
    if ($role instanceof RoleInterface){
      $role->setId($maxRoleLocalId);
      $role->setLibelle($params['libelle']);
      $this->em->persist($role);
      $this->em->flush();
    }
    return $this->redirectToRoute('core_application_role');
  }

  public function removeRoleAction($id)
  {
    if ($id >= 1000){
      $role = $this->em->getRepository(RoleInterface::class)->find($id);
      if ($role instanceof RoleInterface){
        try {
          $this->em->remove($role);
          $this->em->flush();
          $this->addFlash('success', 'Rôle supprimé avec succès');

        } catch (Exception $e){
          $this->addFlash('error', 'Une erreur est survenue lors de la suppression de ce rôle : '.$e->getMessage());
        }
      }
    } else {
      $this->addFlash('error', 'Seuls les rôles locaux sont supprimables');
    }

    return $this->redirectToRoute('core_application_role');
  }

  public function editRoleAction($id)
  {
    if ($id >= 1000){
      $role = $this->em->getRepository(RoleInterface::class)->find($id);
      if ($role instanceof RoleInterface){

        $modules = $this->em->getRepository(ModuleInterface::class)->findAll();
        $notifications = $this->em->getRepository(NotificationInterface::class)->findAll();
        $fonctionsWithoutModule = $this->em->getRepository(FonctionInterface::class)->findBy(['module' => null]);

        return $this->render("@ImanagingCoreApplication/Role/edit.html.twig", [
          'role' => $role,
          'modules' => $modules,
          'notifications' => $notifications,
          'fonctions_without_module' => $fonctionsWithoutModule,
          'basePath' => $this->coreApplication->getBasePath()
        ]);
      }
    } else {
      $this->addFlash('error', 'Seuls les rôles locaux sont éditables');
    }
    return $this->redirectToRoute('core_application_role');
  }

  public function saveRoleAction($id, Request $request)
  {
    $params = $request->request->all();
    if ($id >= 1000){
      $role = $this->em->getRepository(RoleInterface::class)->find($id);
      if ($role instanceof RoleInterface){

        $modules = [];
        $fonctions = [];
        $notifications = [];
        if (isset($params['modules'])){
          foreach ($params['modules'] as $moduleId){
            $module = $this->em->getRepository(ModuleInterface::class)->find($moduleId);
            if ($module instanceof ModuleInterface){
              $modules[] = $module;
            }
          }
        }
        if (isset($params['fonctions'])){
          foreach ($params['fonctions'] as $fonctionId){
            $fonction = $this->em->getRepository(FonctionInterface::class)->find($fonctionId);
            if ($fonction instanceof FonctionInterface){
              $fonctions[] = $fonction;
            }
          }
        }
        if (isset($params['notifications'])){
          foreach ($params['notifications'] as $notificationId){
            $notification = $this->em->getRepository(NotificationInterface::class)->find($notificationId);
            if ($notification instanceof NotificationInterface){
              $notifications[] = $notification;
            }
          }
        }
        $role->setModules($modules);
        $role->setFonctions($fonctions);
        $role->setNotifications($notifications);

        $this->em->persist($role);
        $this->em->flush();
      }
    }
    return $this->redirectToRoute('core_application_role');
  }
}