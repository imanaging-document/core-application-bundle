<?php

namespace Imanaging\CoreApplicationBundle\Controller;

use App\Entity\Destinataire;
use Doctrine\Common\Collections\ArrayCollection;
use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Controller\ImanagingController;
use Imanaging\ZeusUserBundle\Interfaces\AlerteMailInterface;
use Imanaging\ZeusUserBundle\Interfaces\DestinataireMailInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AlerteMailController extends ImanagingController
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
    if (!$this->userCanAccess($this->getUser(), ['core_application_alerte_mail'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }
    
    return $this->render("@ImanagingCoreApplication/AlerteMail/index.html.twig", [
      'destinataires' => $this->em->getRepository(DestinataireMailInterface::class)->findAll(),
      'alertsMailType' => $this->em->getRepository(AlerteMailInterface::class)->findAll(),
      'usersNonZeus' => $this->em->getRepository(UserInterface::class)->findBy(['utilisateurZeus' => false]),
      'basePath' => $this->coreApplication->getBasePath()
    ]);
  }

  public function addDestinataireAction(Request $request)
  {
    if (!$this->userCanAccess($this->getUser(), ['core_application_alerte_mail'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();

    $user = $this->em->getRepository(UserInterface::class)->find($params['user_id']);
    if ($user instanceof UserInterface){
      if ($user->isUtilisateurZeus()){
        $this->addFlash('error', 'Impossible de définir cet utilisateur en tant que destinataire : '.$user->getNom().' '.$user->getPrenom());
        return $this->redirectToRoute('core_application_alerte_mail');
      }
      $destinairesFound = $this->em->getRepository(DestinataireMailInterface::class)->findBy(['user' => $user]);
      if (count($destinairesFound) > 0){
        $this->addFlash('error', 'Cet utilisateur semble déjà être un destinataire : '.$user->getNom().' '.$user->getPrenom());
        return $this->redirectToRoute('core_application_alerte_mail');
      }
    }

    if (filter_var($params['mail'], FILTER_VALIDATE_EMAIL)){
      $destinairesFound = $this->em->getRepository(DestinataireMailInterface::class)->findBy(['mail' => $params['mail']]);
      if (count($destinairesFound) > 0){
        $this->addFlash('error', 'Cette adresse e-mail est déjà utilisée : '.$params['mail']);
        return $this->redirectToRoute('core_application_alerte_mail');
      }

      $className = $this->em->getRepository(DestinataireMailInterface::class)->getClassName();
      $destinataire = new $className();
      if ($destinataire instanceof DestinataireMailInterface){
        if ($user instanceof UserInterface){
          $destinataire->setUser($user);
        }

        $destinataire->setPrenom($params['prenom']);
        $destinataire->setNom($params['nom']);
        $destinataire->setMail($params['mail']);
        $destinataire->setNumero($params['tel_por']);
        $this->em->persist($destinataire);
        $this->em->flush();
        $this->addFlash('success', 'Destinataire ajouté avec succès');
      }
    } else {
      $this->addFlash('error', 'L\'adresse e-mail saisie est invalide : '.$params['mail']);
      return $this->redirectToRoute('core_application_alerte_mail');
    }

    return $this->redirectToRoute('core_application_alerte_mail');
  }

  public function editDestinataireAction(Request $request)
  {
    if (!$this->userCanAccess($this->getUser(), ['core_application_alerte_mail'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();
    $destinaire = $this->em->getRepository(DestinataireMailInterface::class)->find($params['destinataire_id']);

    if ($destinaire instanceof DestinataireMailInterface){
      return $this->render("@ImanagingCoreApplication/AlerteMail/edit_destinataire.html.twig", [
        'destinataire' => $destinaire,
        'usersNonZeus' => $this->em->getRepository(UserInterface::class)->findBy(['utilisateurZeus' => false])
      ]);
    } else {
      return new JsonResponse(['error_message' => 'Destinataire introuvable'], 500);
    }
  }

  public function saveDestinataireAction($id, Request $request)
  {
    if (!$this->userCanAccess($this->getUser(), ['core_application_alerte_mail'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $destinataire = $this->em->getRepository(DestinataireMailInterface::class)->find($id);
    if ($destinataire instanceof DestinataireMailInterface){
      $params = $request->request->all();
      if (filter_var($params['mail'], FILTER_VALIDATE_EMAIL)){
        if ($params['mail'] != $destinataire->getMail()){
          $destinatairesFound = $this->em->getRepository(DestinataireMailInterface::class)->findBy(['mail' => $params['mail']]);
          if (count($destinatairesFound) > 0){
            $this->addFlash('error', 'Cette adresse e-mail est déjà utilisée : '.$params['mail']);
            return $this->redirectToRoute('core_application_alerte_mail');
          }
        }

        $destinataire->setPrenom($params['prenom']);
        $destinataire->setNom($params['nom']);
        $destinataire->setMail($params['mail']);
        $destinataire->setNumero($params['tel_por']);
        $this->em->persist($destinataire);
        $this->em->flush();
        $this->addFlash('success', 'Destinataire mis à jour avec succès');
      } else {
        $this->addFlash('error', 'L\'adresse e-mail saisie est invalide : '.$params['mail']);
        return $this->redirectToRoute('core_application_alerte_mail');
      }
    } else {
      $this->addFlash('error', 'Destinataire introuvable');
    }
    return $this->redirectToRoute('core_application_alerte_mail');
  }

  public function saveDestinataireAlerteMailAction(Request $request)
  {
    if (!$this->userCanAccess($this->getUser(), ['core_application_alerte_mail'])){
      return $this->redirectToRoute($this->coreApplication->getUrlHomepage());
    }

    $params = $request->request->all();

    $destinataires = $this->em->getRepository(DestinataireMailInterface::class)->findAll();
    foreach ($destinataires as $destinataire){
      if ($destinataire instanceof DestinataireMailInterface){
        if (is_null($destinataire->getUser()) || !$destinataire->getUser()->isUtilisateurZeus()){
          $destinataire->setAlertesMail(new ArrayCollection());
          $this->em->persist($destinataire);
        }
      }
    }

    foreach ($params as $key => $value) {
      $parts = explode('~', $key);
      $typeAlertMail = $this->em->getRepository(AlerteMailInterface::class)->find($parts[0]);
      $destinaire = $this->em->getRepository(DestinataireMailInterface::class)->find($parts[1]);
      if ($destinaire instanceof DestinataireMailInterface && $typeAlertMail instanceof AlerteMailInterface) {
        $destinaire->addTypeAlertMail($typeAlertMail);
        $this->em->persist($destinaire);
      }
    }
    $this->em->flush();

    $this->addFlash('success', 'Modification enregitrée');

    return $this->redirectToRoute('core_application_alerte_mail');
  }
}