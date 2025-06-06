<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use App\Entity\RoleModule;
use Exception;
use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Controller\ImanagingController;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\NotificationInterface;
use Imanaging\ZeusUserBundle\Interfaces\FonctionInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleModuleInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class RoleController extends ImanagingController
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

  public function indexAction(Request $request)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();
    return new Response($this->twig->render("@ImanagingCoreApplication/Role/index.html.twig", [
      'roles' => $this->em->getRepository(RoleInterface::class)->findAll(),
      'basePath' => $this->coreApplication->getBasePath()
    ]));
  }

  public function editRolesEnMasseAction()
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    return new Response($this->twig->render("@ImanagingCoreApplication/Role/mass-edit.html.twig", [
      'roles' => $this->em->getRepository(RoleInterface::class)->findAll(),
      'root_modules' => $this->em->getRepository(ModuleInterface::class)->findBy(['parent' => null]),
      'standalone_fonctions' => $this->em->getRepository(FonctionInterface::class)->findBy(['module' => null]),
      'basePath' => $this->coreApplication->getBasePath()
    ]));
  }

  public function editRolesEnMasseSaveModuleAction(Request $request)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();
    $role = $this->em->getRepository(RoleInterface::class)->find($params['role_id']);
    $module = $this->em->getRepository(ModuleInterface::class)->find($params['module_id']);
    if ($role instanceof RoleInterface && $module instanceof ModuleInterface){
      if ($params['checked'] == 'true'){
        // On ajoute le module s'il n'y est pas déjà
        $roleModule = $this->em->getRepository(RoleModuleInterface::class)->findOneBy(['role' => $role, 'module' => $module]);
        if (!($roleModule instanceof RoleModuleInterface)){
          $className = $this->em->getRepository(RoleModuleInterface::class)->getClassName();
          $roleModule = new $className();
          $roleModule->setModule($module);
          $roleModule->setRole($role);
          $roleModule->setAcces(true);
          $roleModule->setLibelle($module->getLibelle());
          $roleModule->setOrdre($module->getOrdre());
          $this->em->persist($roleModule);
        }
      } else {
        // On supprime le module
        $roleModule = $this->em->getRepository(RoleModuleInterface::class)->findOneBy(['role' => $role, 'module' => $module]);
        if ($roleModule instanceof RoleModuleInterface){
          $this->em->remove($roleModule);
        }
      }
      $this->em->flush();
      return $this->json([]);
    } else {
      return $this->json(['error_message' => 'Module ou rôle introuvable'], 500);
    }
  }

  public function editRolesEnMasseSaveFonctionAction(Request $request)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();
    $role = $this->em->getRepository(RoleInterface::class)->find($params['role_id']);
    $fonction = $this->em->getRepository(FonctionInterface::class)->find($params['fonction_id']);
    if ($role instanceof RoleInterface && $fonction instanceof FonctionInterface){
      $fonctions = $role->getFonctions();
      if ($params['checked'] == 'true'){
        // On ajoute le module s'il n'y est pas déjà
        $fonctions->add($fonction);
        $role->setFonctions($fonctions);
      } else {
        // On supprime le module
        $fonctions->removeElement($fonction);
        $role->setFonctions($fonctions);
      }
      $this->em->persist($role);
      $this->em->flush();
      return $this->json([]);
    } else {
      return $this->json(['error_message' => 'Module ou rôle introuvable'], 500);
    }
  }

  public function addRoleAction(Request $request)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();

    if (isset($params['par-defaut'])){
      $roles = $this->em->getRepository(RoleInterface::class)->findBy(['zeusOnly' => false, 'parDefaut' => true]);
      foreach ($roles as $role){
        if ($role instanceof RoleInterface){
          $role->setParDefaut(false);
          $this->em->persist($role);
        }
      }
    }

    $className = $this->em->getRepository(RoleInterface::class)->getClassName();
    $role = new $className();
    if ($role instanceof RoleInterface){
      $role->setCode('');
      $role->setLibelle($params['libelle']);
      $role->setZeusOnly(false);
      $role->setParDefaut(isset($params['par-defaut']));
      $this->em->persist($role);
      $this->em->flush();
    }
    return $this->redirectToRoute('core_application_role');
  }

  public function removeRoleAction($id)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $role = $this->em->getRepository(RoleInterface::class)->find($id);
    if ($role instanceof RoleInterface){
      if (!$role->isZeusOnly()){
        try {
          $this->em->remove($role);
          $this->em->flush();
          $this->addFlash('success', 'Rôle supprimé avec succès');

        } catch (Exception $e){
          $this->addFlash('error', 'Une erreur est survenue lors de la suppression de ce rôle : '.$e->getMessage());
        }
      } else {
        $this->addFlash('error', 'Seuls les rôles non ZEUS peuvent être supprimés.');
      }
    }
    return $this->redirectToRoute('core_application_role');
  }

  public function editRoleAction($id)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $role = $this->em->getRepository(RoleInterface::class)->find($id);
    if ($role instanceof RoleInterface){
      if (!$role->isZeusOnly()){
        $modules = $this->em->getRepository(ModuleInterface::class)->findAll();
        $notifications = $this->em->getRepository(NotificationInterface::class)->findAll();
        $fonctionsWithoutModule = $this->em->getRepository(FonctionInterface::class)->findBy(['module' => null]);

        return new Response($this->twig->render("@ImanagingCoreApplication/Role/edit.html.twig", [
          'role' => $role,
          'modules' => $modules,
          'notifications' => $notifications,
          'fonctions_without_module' => $fonctionsWithoutModule,
          'basePath' => $this->coreApplication->getBasePath()
        ]));
      } else {
        $modules = $this->em->getRepository(ModuleInterface::class)->findAll();
        $notifications = $this->em->getRepository(NotificationInterface::class)->findAll();
        $fonctionsWithoutModule = $this->em->getRepository(FonctionInterface::class)->findBy(['module' => null]);

        return new Response($this->twig->render("@ImanagingCoreApplication/Role/edit-zeus.html.twig", [
          'role' => $role,
          'modules' => $modules,
          'notifications' => $notifications,
          'fonctions_without_module' => $fonctionsWithoutModule,
          'basePath' => $this->coreApplication->getBasePath()
        ]));
      }
    }
    return $this->redirectToRoute('core_application_role');
  }

  public function saveLibelleRoleAction($id, Request $request)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();
    $role = $this->em->getRepository(RoleInterface::class)->find($id);
    if ($role instanceof RoleInterface){
      if (!$role->isZeusOnly()) {
        if (isset($params['par-defaut'])){
          $roles = $this->em->getRepository(RoleInterface::class)->findBy(['zeusOnly' => false, 'parDefaut' => true]);
          foreach ($roles as $role){
            if ($role instanceof RoleInterface){
              $role->setParDefaut(false);
              $this->em->persist($role);
            }
          }
        }

        $role->setLibelle($params['libelle']);
        $role->setParDefaut(isset($params['par-defaut']));
        $this->em->persist($role);
        $this->em->flush();
      } else {
        $this->addFlash('error', 'Seuls les rôles non ZEUS sont éditables.');
      }
    }
    return $this->redirectToRoute('core_application_role');
  }

  public function saveRoleAction($id, Request $request)
  {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();
    $role = $this->em->getRepository(RoleInterface::class)->find($id);
    if ($role instanceof RoleInterface){
      if (!$role->isZeusOnly()){
        $fonctions = [];
        $notifications = [];
        if (isset($params['modules'])){
          // TOUT D'ABORD ON VIRE TOUS LES MODULES QUI NE SONT PAS DANS LA LISTE

          $modules = $this->em->getRepository(ModuleInterface::class)->findAll();
          foreach ($modules as $module){
            if ($module instanceof ModuleInterface){
              if (!in_array($module->getId(), $params['modules'])){
                $roleModule = $this->em->getRepository(RoleModuleInterface::class)->findOneBy(['role' => $role, 'module' => $module]);
                if ($roleModule instanceof RoleModuleInterface){
                  $this->em->remove($roleModule);
                }
              }
            }
          }

          foreach ($params['modules'] as $moduleId){
            $module = $this->em->getRepository(ModuleInterface::class)->find($moduleId);
            if ($module instanceof ModuleInterface){
              $roleModule = $this->em->getRepository(RoleModuleInterface::class)->findOneBy(['role' => $role, 'module' => $module]);
              if (!($roleModule instanceof RoleModuleInterface)){
                $className = $this->em->getRepository(RoleModuleInterface::class)->getClassName();
                $roleModule = new $className();
                $roleModule->setModule($module);
                $roleModule->setRole($role);
                $roleModule->setAcces(true);
                $roleModule->setLibelle($module->getLibelle());
                $roleModule->setOrdre($module->getOrdre());
                $this->em->persist($roleModule);
              }
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
        $role->setFonctions($fonctions);
        $role->setNotifications($notifications);

        $this->em->persist($role);
        $this->em->flush();
      } else {
        $this->addFlash('error', 'Seuls les rôles non ZEUS sont éditables.');
      }
    }
    return $this->redirectToRoute('core_application_role');
  }

  public function editRoleModuleAction(Request $request){
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_role'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();
    $role = $this->em->getRepository(RoleInterface::class)->find($params['role_id']);
    $module = $this->em->getRepository(ModuleInterface::class)->find($params['module_id']);
    if ($role instanceof RoleInterface && $module instanceof ModuleInterface){
      $roleModule = $this->em->getRepository(RoleModuleInterface::class)->findOneBy(['role' => $params['role_id'], 'module' => $params['module_id']]);
      if (!($roleModule instanceof RoleModuleInterface)){
        $roleModule = new RoleModule();
        $roleModule->setRole($role);
        $roleModule->setModule($module);
        $roleModule->setAcces(true);
        $roleModule->setLibelle($module->getLibelle());
        $roleModule->setOrdre($module->getOrdre());
        $this->em->persist($roleModule);
      }

      // on récupère toutes les applications par type
      $apps = $this->coreApplication->getApplicationsByType($module->getTypeApplication());
      $newApps = [];
      foreach ($apps as $app) {
        if ($roleModule->hasApp($app['client_traitement'])) {
          $appTemp = $roleModule->getApp($app['client_traitement']);
          $appTemp['url'] = $app['url'];
        } else {
          $appTemp = [
            'client_traitement' => $app['client_traitement'],
            'libelle' => $app['nom'],
            'url' => $app['url'],
            'acces' => false
          ];
        }
        $newApps[] = $appTemp;
      }
      $roleModule->setApps($newApps);
      $this->em->persist($roleModule);

      $this->em->flush();
      if ($roleModule->getRole()->isZeusOnly()) {
        return new Response($this->twig->render("@ImanagingCoreApplication/Role/modals/edit-role-module-zeus.html.twig", [
          'role_module' => $roleModule
        ]));
      } else {
        return new Response($this->twig->render("@ImanagingCoreApplication/Role/modals/edit-role-module.html.twig", [
          'role_module' => $roleModule
        ]));
      }
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function saveRoleModuleAction($id, Request $request){
    $roleModule = $this->em->getRepository(RoleModuleInterface::class)->find($id);
    if ($roleModule instanceof RoleModuleInterface){
      $params = $request->request->all();
      if (!$roleModule->getRole()->isZeusOnly()) {
        $roleModule->setAcces(isset($params['acces']));
        $roleModule->setLibelle($params['libelle']);
        $roleModule->setOrdre($params['ordre']);
      }

      $apps = $roleModule->getApps();
      foreach ($apps as $key => $app) {
        $app['libelle'] = $params['app_libelle_' . $app['client_traitement']];
        $app['acces'] = array_key_exists('app_acces_' . $app['client_traitement'], $params);
        $apps[$key] = $app;
      }
      $roleModule->setApps($apps);
      $this->em->persist($roleModule);
      $this->em->flush();

      return $this->redirectToRoute('core_application_role_edit', [
        'id' => $roleModule->getRole()->getId()
      ]);
    } else {
      return new JsonResponse([], 500);
    }
  }
}