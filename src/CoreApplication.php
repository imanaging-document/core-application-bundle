<?php

namespace Imanaging\CoreApplicationBundle;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Imanaging\ApiCommunicationBundle\ApiCoreCommunication;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CoreApplication
{
  private $em;
  private $apiCoreCommunication;
  private $session;

  /**
   * @param EntityManagerInterface $em
   * @param ApiCoreCommunication $apiCoreCommunication
   * @param SessionInterface $session
   */
  public function __construct(EntityManagerInterface $em, ApiCoreCommunication $apiCoreCommunication, SessionInterface $session)
  {
    $this->em = $em;
    $this->apiCoreCommunication = $apiCoreCommunication;
    $this->session = $session;
  }

  public function declaration()
  {
    $tokenAndDate = $this->getCoreTokenAndDate();
    $tokenCoreHashed = $tokenAndDate['token'];
    $tokenCoreDate = $tokenAndDate['date'];

    $json_data = new \stdClass();
    $json_data->token = getenv('APP_SECRET');
    $json_data->nom = getenv('APP_NAME');
    $json_data->url = getenv('OWN_URL');
    $json_data->url_api = getenv('OWN_URL_API');
    $json_data->client_traitement = getenv('CLIENT_TRAITEMENT');
    $json_data->type_application = getenv('CORE_API_TYPE_APPLICATION');

    // Generating post data
    $postData = array(
      'token' => $tokenCoreHashed,
      'token_date' => $tokenCoreDate,
      'json_data' => json_encode($json_data)
    );
    $url = '/application/ajout';
    return $this->apiCoreCommunication->sendPostRequest($url, $postData);
  }

  /**
   * Ajout d'un nouveau segment
   */
  public function synchroniserUsers(){
    $tokenAndDate = $this->getCoreTokenAndDate();
    $tokenCoreHashed = $tokenAndDate['token'];
    $tokenCoreDate = $tokenAndDate['date'];
    $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application='.getenv('CORE_API_TYPE_APPLICATION').'&client_traitement='.getenv('CLIENT_TRAITEMENT');
    $response = $this->apiCoreCommunication->sendGetRequest($url);


    if ($response->getHttpCode() == 200) {
      $data = json_decode($response->getData());
      $applicationId = $data->id;

      $url = '/synchronisation-utlisateurs?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&application='.$applicationId;
      $response = $this->apiCoreCommunication->sendGetRequest($url);

      if ($response->getHttpCode() == 200){
        $utilisateursLocauxCore = $this->em->getRepository(User::class)->findBy(['utilisateurCore' => true]);
        foreach ($utilisateursLocauxCore as $user) {
          $user->setActif = false;
          $this->em->persist($user);
        }
        $utilisateurs = json_decode($response->getData());
        foreach ($utilisateurs as $utilisateur) {
          $utilisateurLocalCore = $this->em->getRepository(User::class)->findOneBy(['utilisateurCore' => true, 'login' => $utilisateur->login]);
          if (!($utilisateurLocalCore instanceof User)) {
            $userAlreadyExist = $this->em->getRepository(User::class)->findOneBy(['login' => $utilisateur->login]);
            if ($userAlreadyExist instanceof User) {
              return ['success' => false, 'error_message' => "Un utilisateur local existe déjà pour ce login (" . $utilisateur->login . ")"];
            } else {
              $utilisateurLocalCore = new User();
              $utilisateurLocalCore->setUtilisateurCore(true);
              $utilisateurLocalCore->setUtilisateurZeus(false);
              $utilisateurLocalCore->setLogin($utilisateur->login);

            }
          }

          $utilisateurLocalCore->setActif(true);
          $utilisateurLocalCore->setPrenom($utilisateur->prenom);
          $utilisateurLocalCore->setNom($utilisateur->nom);
          $utilisateurLocalCore->setUsername($utilisateur->username);
          $utilisateurLocalCore->setMail($utilisateur->mail);
          $this->em->persist($utilisateurLocalCore);
        }

        $this->em->flush();
        return ['success' => true];
      } else {
        return ['success' => false, 'error_message' => "Une erreur est survenue lors de la récupération des utilisateurs. (Code HTTP : "  . $response->getHttpCode() . ')'];
      }
    } else {
      return ['success' => false, 'error_message' => "Une erreur est survenue lors de la récupération de l'application."];
    }
  }

  /**
   * @param User $user
   * @return array
   */
  public function getMenuApplications(User $user)
  {
    $keyMenu = 'menu_applications';
    $applications = $this->session->get($keyMenu);
    if (!is_null($applications)) {
      return json_decode($applications, true);
    }


    $tokenAndDate = $this->getCoreTokenAndDate();
    $tokenCoreHashed = $tokenAndDate['token'];
    $tokenCoreDate = $tokenAndDate['date'];

    // on va récupèrer directement sur le CORE les applications disponibles pour cet utilisateur
    $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application='.getenv('CORE_API_TYPE_APPLICATION').'&client_traitement='.getenv('CLIENT_TRAITEMENT');
    $response = $this->apiCoreCommunication->sendGetRequest($url);
    if ($response->getHttpCode() == 200) {
      $data = json_decode($response->getData());
      $applicationId = $data->id;
      $url = '/applications-utilisateur?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&application='.$applicationId.'&login='.$user->getLogin();
      $resultRequest = $this->apiCoreCommunication->sendGetRequest($url);
      if ($resultRequest->getHttpCode() == 200) {
        $applications = json_decode($resultRequest->getData(), true);
        $this->session->set($keyMenu, json_encode($applications));
      } else {
        $applications = [];
      }
    } else {
      $applications = [];
    }

    return $applications;
  }

  public function canLog(User $user, $password)
  {
    $tokenAndDate = $this->getCoreTokenAndDate();
    $tokenCoreHashed = $tokenAndDate['token'];
    $tokenCoreDate = $tokenAndDate['date'];

    // on va demander au CORE si cet utilisateur peut se connecter sur cette application
    $url = '/application-hqmc?token='.$tokenCoreHashed.'&token_date'.$tokenCoreDate.'&client_traitement='.getenv('CLIENT_TRAITEMENT');
    $response = $this->apiCoreCommunication->sendGetRequest($url);
    if ($response->getHttpCode() == 200) {
      $data = json_decode($response->getData());
      $applicationId = $data->id;
      $url = '/can-log-utilisateur?token='.$tokenCoreHashed.'&token_date'.$tokenCoreDate.'&application=' . $applicationId . '&login=' . $user->getLogin().'&password='.hash('sha256', $password);
      $resultRequest = $this->apiCoreCommunication->sendGetRequest($url);
      if ($resultRequest->getHttpCode() == 200) {
        return true;
      }
    }
    return false;
  }

  private function getCoreTokenAndDate() {
    $now = new DateTime();
    $nowFormat = $now->format('YmdHis');
    return [
      'token' => hash('sha256', $nowFormat.getenv('CORE_API_TOKEN')),
      'date' => $nowFormat
    ];
  }
}
