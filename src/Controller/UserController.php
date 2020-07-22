<?php


namespace Imanaging\CoreApplicationBundle\Controller;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
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

  public function indexAction(Request $request)
  {
    $params = $request->request->all();
    return $this->render("@ImanagingCoreApplication/User/index.html.twig", [
      'utilisateurs' => $utilisateurs = $this->em->getRepository(UserInterface::class)->findAll(),
      'basePath' => $params['basePath']
    ]);
  }

  public function synchroniserAction()
  {
    $res = $this->coreApplication->synchroniserUsers();
    if ($res['success']){
      $this->addFlash('success', 'Les utilisateurs ont bien été synchronisés.');
    } else {
      $this->addFlash('error', $res['error_message']);
    }
    return $this->redirectToRoute('core_application_user');
  }
}