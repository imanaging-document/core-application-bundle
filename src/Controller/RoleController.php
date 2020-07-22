<?php


namespace Imanaging\CoreApplicationBundle\Controller;

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
    return $this->render("@ImanagingCoreApplication/User/index.html.twig", [
      'roles' => $this->em->getRepository(RoleInterface::class)->findAll(),
      'basePath' => $params['basePath']
    ]);
  }

  public function addRoleAction(Request $request)
  {
    $params = $request->request->all();

    $className = $this->em->getRepository(RoleInterface::class)->getClassName();
    $role = new $className();
    if ($role instanceof RoleInterface){
      $role->setLibelle($params['libelle']);
      $this->em->persist($role);
      $this->em->flush();
    }
    return $this->redirectToRoute('core_application_role');
  }
}