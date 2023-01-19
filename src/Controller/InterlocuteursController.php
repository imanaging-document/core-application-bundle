<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use App\Entity\CoreSynchronisationAction;
use App\Entity\HierarchiePatrimoineType;
use App\Entity\Interlocuteur;
use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\CoreApplicationBundle\Interfaces\CoreSynchronisationActionInterface;
use Imanaging\CoreApplicationBundle\Interfaces\HierarchiePatrimoineInterface;
use Imanaging\CoreApplicationBundle\Interfaces\HierarchiePatrimoineTypeInterface;
use Imanaging\CoreApplicationBundle\Interfaces\InterlocuteurContratInterface;
use Imanaging\CoreApplicationBundle\Interfaces\InterlocuteurInterface;
use Imanaging\CoreApplicationBundle\Interfaces\InterlocuteurTypeInterface;
use Imanaging\ZeusUserBundle\Controller\ImanagingController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class InterlocuteursController extends ImanagingController
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
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_interlocuteurs'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }
    $types = [];
    foreach ($this->em->getRepository(InterlocuteurTypeInterface::class)->findBy([], ['id' => 'ASC']) as $type) {
      if ($type instanceof InterlocuteurTypeInterface) {
        $types[] = [
          'id' => $type->getId(),
          'libelle' => $type->getLibelle(),
        ];
      }
    }
    return new Response($this->twig->render("@ImanagingCoreApplication/Interlocuteurs/index.html.twig", [
      'interlocuteurs_types' => $types,
      'historiques_synchro' => $this->em->getRepository(CoreSynchronisationActionInterface::class)->findBy(
        ['typeSynchronisation' => CoreSynchronisationActionInterface::TYPE_SYNCHRONISATION_INTERLOCUTEURS], ['dateCreation' => 'DESC'], 10),
      'basePath' => $this->coreApplication->getBasePath()
    ]));
  }

  public function showInterlocuteursListAction(Request $request) {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_hierarchie_patrimoine'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $idType = $request->request->get('id-type');
    $interlocuteursFormatted = [];
    $interlocuteurType = $this->em->getRepository(InterlocuteurTypeInterface::class)->find($idType);
    if ($interlocuteurType instanceof InterlocuteurTypeInterface) {
      $interlocuteurs = $this->em->getRepository(InterlocuteurInterface::class)->findBy(['type' => $idType]);
      foreach ($interlocuteurs as $interlocuteur) {
        if ($interlocuteur instanceof InterlocuteurInterface) {
          $interlocuteursFormatted[] = [
            'libelle' => $interlocuteur->getLibelle(),
            'nb' => $this->em->getRepository(InterlocuteurContratInterface::class)->count(['interlocuteur' => $interlocuteur])
          ];
        }
      }

      return new Response($this->twig->render("@ImanagingCoreApplication/Interlocuteurs/show-list.html.twig", [
        'interlocuteurs' => $interlocuteursFormatted,
        'type' => $interlocuteurType->getLibelle(),
        'basePath' => $this->coreApplication->getBasePath()
      ]));
    }
    return new JsonResponse(['error_message' => 'Type introuvable'], 500);

  }
}