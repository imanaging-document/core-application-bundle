<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use App\Entity\HierarchiePatrimoine;
use App\Entity\HierarchiePatrimoineType;
use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\CoreApplicationBundle\Interfaces\HierarchiePatrimoineInterface;
use Imanaging\CoreApplicationBundle\Interfaces\HierarchiePatrimoineTypeInterface;
use Imanaging\ZeusUserBundle\Controller\ImanagingController;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class HierarchiePatrimoineController extends ImanagingController
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
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_hierarchie_patrimoine'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }
    $types = [];
    foreach ($this->em->getRepository(HierarchiePatrimoineTypeInterface::class)->findBy([], ['niveau' => 'ASC']) as $type) {
      if ($type instanceof HierarchiePatrimoineTypeInterface) {
        $types[] = [
          'id' => $type->getId(),
          'libelle' => $type->getLibelle(),
          'niveau' => $type->getNiveau(),
          'nb' => $this->em->getRepository(HierarchiePatrimoineInterface::class)->count(['type' => $type]),
        ];
      }
    }
    return new Response($this->twig->render("@ImanagingCoreApplication/HierarchiePatrimoine/index.html.twig", [
      'hierarchie_patrimoine_types' => $types,
      'basePath' => $this->coreApplication->getBasePath()
    ]));
  }

  public function showHierarchiePatrimoineListAction(Request $request) {
    if (!$this->userCanAccess($this->tokenStorage->getToken()->getUser(), ['core_application_hierarchie_patrimoine'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }
    $idType = $request->request->get('id-type');
    $typesFormatted = [];
    $hierarchies = $this->em->getRepository(HierarchiePatrimoineInterface::class)->findBy(['type' => $idType]);
    $hierarchieType = $this->em->getRepository(HierarchiePatrimoineTypeInterface::class)->find($idType);
    if ($hierarchieType instanceof HierarchiePatrimoineTypeInterface) {
      $typeTmp = $hierarchieType;
      do {
        $typesFormatted[$typeTmp->getNiveau()] = [
          'libelle' => $typeTmp->getLibelle(),
          'niveau' => $typeTmp->getNiveau()
        ];
        $typeTmp = $typeTmp->getParent();
      } while ($typeTmp instanceof HierarchiePatrimoineType);
      $hierarchieItems = [];
      foreach ($hierarchies as $hierarchiePatrimoine) {
        if ($hierarchiePatrimoine instanceof HierarchiePatrimoineInterface){
          $hpTemp = $hierarchiePatrimoine;
          $itemTemp = [
            'nb_contrats' => $this->coreApplication->getNbContratsConcernes($hierarchiePatrimoine)
          ];

          do {
            $itemTemp[$hpTemp->getType()->getNiveau()] = $hpTemp;
            $hpTemp = $hpTemp->getParent();
          } while (!is_null($hpTemp));

          $hierarchieItems[] = $itemTemp;
        }
      }

      ksort($typesFormatted);

      return new Response($this->twig->render("@ImanagingCoreApplication/HierarchiePatrimoine/show-hierarchie-patrimoine-list.html.twig", [
        'hierarchies_patrimoines' => $hierarchieItems,
        'types' => $typesFormatted,
        'basePath' => $this->coreApplication->getBasePath()
      ]));
    }
    return new JsonResponse(['error_message' => 'Type introuvable'], 500);

  }
}