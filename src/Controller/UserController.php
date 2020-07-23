<?php


namespace Imanaging\CoreApplicationBundle\Controller;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UserController extends AbstractController
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
    return $this->render("@ImanagingCoreApplication/User/index.html.twig", [
      'utilisateurs' => $this->em->getRepository(UserInterface::class)->findAll(),
      'basePath' => 'base.html.twig'
    ]);
  }

  public function editAction($id)
  {
    $user = $this->em->getRepository(UserInterface::class)->find($id);
    if ($user instanceof UserInterface){
      return $this->render("@ImanagingCoreApplication/User/edit.html.twig", [
        'user' => $user,
        'roles' => $this->em->getRepository(RoleInterface::class)->findAll(),
        'basePath' => 'base.html.twig'
      ]);
    } else {
      $this->addFlash('Error', 'Utilisateur introuvable : '.$id);
      return $this->redirectToRoute('core_application_user');
    }
  }

  public function saveAction($id, Request $request)
  {
    $params = $request->request->all();

    $user = $this->em->getRepository(UserInterface::class)->find($id);
    if ($user instanceof UserInterface) {
      $role = $this->em->getRepository(RoleInterface::class)->find($params['role']);
      if ($role instanceof RoleInterface){
        $user->setNom($params['nom']);
        $user->setPrenom($params['prenom']);
        $user->setMail($params['mail']);
        $user->setRole($role);
        $this->em->persist($user);
        $this->em->flush();
      } else {
        $this->addFlash('Error', 'Rôle introuvable : '.$params['role']);
      }
    } else {
      $this->addFlash('Error', 'Utilisateur introuvable : '.$id);
    }
    return $this->redirectToRoute('core_application_user');
  }

  public function synchroniserAction()
  {
    $res = $this->coreApplication->synchroniserUsers();
    if ($res['success']){
      return new JsonResponse();
    } else {
      return new JsonResponse(['error_message' => $res['error_message']], 500);
    }
  }
}