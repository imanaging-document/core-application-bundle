<?php

namespace Imanaging\CoreApplicationBundle;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Imanaging\ApiCommunicationBundle\ApiCoreCommunication;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RouteInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CoreApplication
{
  private $em;
  private $apiCoreCommunication;
  private $session;
  private $basePath;
  private $urlLogout;
  private $urlProfile;
  private $urlHomepage;

  /**
   * @param EntityManagerInterface $em
   * @param ApiCoreCommunication $apiCoreCommunication
   * @param SessionInterface $session
   */
  public function __construct(EntityManagerInterface $em, ApiCoreCommunication $apiCoreCommunication, SessionInterface $session,
                              $basePath, $urlLogout, $urlProfile, $urlHomepage)
  {
    $this->em = $em;
    $this->apiCoreCommunication = $apiCoreCommunication;
    $this->session = $session;
    $this->basePath = $basePath;
    $this->urlLogout = $urlLogout;
    $this->urlProfile = $urlProfile;
    $this->urlHomepage = $urlHomepage;
  }

  public function getBasePath()
  {
    return $this->basePath;
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
          $user->setActif(false);
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

          // Mise à jour du role
          $role = $this->em->getRepository(RoleInterface::class)->find($utilisateur->role_id);
          $utilisateurLocalCore->setRole($role);

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

  public function getApplicationInformation($moduleId){
    $module = $this->em->getRepository(ModuleInterface::class)->find($moduleId);
    if ($module instanceof ModuleInterface){
      $tokenAndDate = $this->getCoreTokenAndDate();
      $tokenCoreHashed = $tokenAndDate['token'];
      $tokenCoreDate = $tokenAndDate['date'];

      switch ($module->getTypeApplication()){
        case 'dossier_locataire':
          $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=dossier-locataire';
          $response = $this->apiCoreCommunication->sendGetRequest($url);
          if ($response->getHttpCode() == 200) {
            return json_decode($response->getData(), true);
          }
          break;
        case 'portail_extranet':
          $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=portail-extranet';
          $response = $this->apiCoreCommunication->sendGetRequest($url);
          if ($response->getHttpCode() == 200) {
            return json_decode($response->getData(), true);
          }
          break;
        case 'core':

          $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=core';
          $response = $this->apiCoreCommunication->sendGetRequest($url);
          if ($response->getHttpCode() == 200) {
            return json_decode($response->getData(), true);
          }
          break;
        default:
          return [
            'success' => 'false',
            'error_message' => 'type d\'application non géré par les modules'
          ];
      }
    }
    return [
      'success' => 'false',
      'error_message' => 'Une erreur est survenue'
    ];
  }

  /**
   * @param User $user
   * @param bool $isDroite
   * @return array|mixed|null
   */
  public function getTopLevelModules(User $user, bool $isDroite)
  {
    $key = 'menu_'.($isDroite ? 1: 0);
    $topLevelModules = $this->session->get($key);
    if (!is_null($topLevelModules)) {
      return json_decode($topLevelModules);
    }

    $modules = $this->em->getRepository(ModuleInterface::class)->findBy(['parent' => null, 'droite' => $isDroite]);
    $role = $user->getRole();
    $topLevelModules = [];
    foreach ($modules as $module) {
      if ($module instanceof ModuleInterface) {
        $roleModule = $role->getRoleModule($module);
        if ($roleModule instanceof RoleModuleInterface){
          $topLevelModules[] = [
            'id' => $module->getId(),
            'libelle' => $roleModule->getLibelle(),
            'type' => $module->getTypeApplication(),
            'data_application' => json_decode($module->getDataApplication()),
            'route' => $module->getRoute(),
            'children' => $this->getChildren($module, $role),
            'redirection_route' => $module->getRedirectionRoute()
          ];
        }
      }
    }
    $this->session->get($key, json_encode($topLevelModules));
    return $topLevelModules;
  }

  /**
   * @param UserInterface $user
   * @param $routeName
   * @return array|mixed|null
   */
  public function getModulesLevel2ByRoute(UserInterface $user, $routeName)
  {
    $key = 'menu_'.$routeName;
    $secondLevelModules = $this->session->get($key);
    if (!is_null($secondLevelModules)) {
      return json_decode($secondLevelModules);
    }

    $route = $this->em->getRepository(RouteInterface::class)->findOneBy(['route' => $routeName]);
    if ($route instanceof RouteInterface){
      $role = $user->getRole();
      $module = $this->em->getRepository(ModuleInterface::class)->findOneBy(['code' => $route->getCodeModule()]);
      if ($module instanceof ModuleInterface) {
        // Il ne doit s'agit que de module de niveau 1
        if (is_null($module->getParent())){
          $secondLevelModules = [];
          foreach ($module->getEnfants() as $moduleEnfant){
            $roleModule = $role->getRoleModule($moduleEnfant);
            if ($roleModule instanceof RoleModuleInterface){
              $secondLevelModules[] = [
                'id' => $moduleEnfant->getId(),
                'libelle' => $roleModule->getLibelle(),
                'type' => $moduleEnfant->getTypeApplication(),
                'data_application' => json_decode($moduleEnfant->getDataApplication()),
                'route' => $moduleEnfant->getRoute(),
                'children' => $this->getChildren($moduleEnfant, $role),
                'redirection_route' => $moduleEnfant->getRedirectionRoute()
              ];
            }
          }

          $this->session->set($key, json_encode($secondLevelModules));
          return $secondLevelModules;
        }
      }
    }
    return [];
  }

  /**
   * @param ModuleInterface $module
   * @param RoleInterface $role
   * @return array
   */
  private function getChildren(ModuleInterface $module, RoleInterface $role)
  {
    $children = [];
    foreach ($module->getEnfants() as $enfant) {
      if ($enfant instanceof ModuleInterface){
        if ($role->hasModule($enfant)) {
          $children[] = [
            'id' => $enfant->getId(),
            'libelle' => $enfant->getLibelle(),
            'type' => $module->getTypeApplication(),
            'data_application' => json_decode($module->getDataApplication()),
            'route' => $enfant->getRoute(),
            'children' => $this->getChildren($enfant, $role)
          ];
        }
      }
    }
    return $children;
  }

  public function getUrlLogout(){
    return $this->urlLogout;
  }

  public function getUrlProfile(){
    return $this->urlProfile;
  }

  public function getUrlHomepage(){
    return $this->urlHomepage;
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

  public function getCoreTokenAndDate() {
    $now = new DateTime();
    $nowFormat = $now->format('YmdHis');
    return [
      'token' => hash('sha256', $nowFormat.getenv('CORE_API_TOKEN')),
      'date' => $nowFormat
    ];
  }
}
