<?php


namespace Imanaging\CoreApplicationBundle\Controller;

use Exception;
use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
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
      'basePath' => 'base.html.twig'
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
    return $this->redirectToRoute('core_application_role');
  }
}