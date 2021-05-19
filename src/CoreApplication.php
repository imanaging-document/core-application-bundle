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
use Imanaging\ZeusUserBundle\Interfaces\ParametrageInterface;
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
  private $appSecret;
  private $appName;
  private $ownUrl;
  private $ownUrlApi;
  private $clientTraitement;
  private $traitementYear;
  private $coreApiType;
  private $urlUpdatePassword;

  /**
   * @param EntityManagerInterface $em
   * @param ApiCoreCommunication $apiCoreCommunication
   * @param SessionInterface $session
   * @param $basePath
   * @param $urlLogout
   * @param $urlProfile
   * @param $urlHomepage
   * @param $appSecret
   * @param $appName
   * @param $ownUrl
   * @param $ownUrlApi
   * @param $clientTraitement
   * @param $traitementYear
   * @param $coreApiType
   * @param $urlUpdatePassword
   */
  public function __construct(EntityManagerInterface $em, ApiCoreCommunication $apiCoreCommunication, SessionInterface $session,
                              $basePath, $urlLogout, $urlProfile, $urlHomepage, $appSecret, $appName, $ownUrl, $ownUrlApi,
                              $clientTraitement, $traitementYear, $coreApiType, $urlUpdatePassword)
  {
    $this->em = $em;
    $this->apiCoreCommunication = $apiCoreCommunication;
    $this->session = $session;
    $this->basePath = $basePath;
    $this->urlLogout = $urlLogout;
    $this->urlProfile = $urlProfile;
    $this->urlHomepage = $urlHomepage;
    $this->appSecret = $appSecret;
    $this->appName = $appName;
    $this->ownUrl = $ownUrl;
    $this->ownUrlApi = $ownUrlApi;
    $this->clientTraitement = $clientTraitement;
    $this->traitementYear = $traitementYear;
    $this->coreApiType = $coreApiType;
    $this->urlUpdatePassword = $urlUpdatePassword;
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
    $json_data->token = $this->appSecret;
    $json_data->nom = $this->appName;
    $json_data->url = $this->ownUrl;
    $json_data->url_api = $this->ownUrlApi;
    $json_data->client_traitement = $this->clientTraitement;
    $json_data->traitement_year = $this->traitementYear;
    $json_data->type_application = $this->coreApiType;

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
    $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application='.$this->coreApiType.'&client_traitement='.$this->clientTraitement;
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
    $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application='.$this->coreApiType.'&client_traitement='.$this->clientTraitement;
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

  public function getApplicationInformation($moduleId, $clientTraitement = null){
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
        case 'quittance':
          $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=quittance';
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
        case 'hqmc':
          $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=hqmc&client_traitement='.$clientTraitement;
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

  public function getApplicationsByType($type){
    $tokenAndDate = $this->getCoreTokenAndDate();
    $tokenCoreHashed = $tokenAndDate['token'];
    $tokenCoreDate = $tokenAndDate['date'];

    switch ($type) {
      case 'dossier_locataire':
        $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=dossier-locataire&multiple=1';
        $response = $this->apiCoreCommunication->sendGetRequest($url);
        if ($response->getHttpCode() == 200) {
          return json_decode($response->getData(), true);
        }
        break;
      case 'portail_extranet':
        $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=portail-extranet&multiple=1';
        $response = $this->apiCoreCommunication->sendGetRequest($url);
        if ($response->getHttpCode() == 200) {
          return json_decode($response->getData(), true);
        }
        break;
      case 'quittance':
        $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=quittance&multiple=1';
        $response = $this->apiCoreCommunication->sendGetRequest($url);
        if ($response->getHttpCode() == 200) {
          return json_decode($response->getData(), true);
        }
        break;
      case 'core':
        $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=core&multiple=1';
        $response = $this->apiCoreCommunication->sendGetRequest($url);
        if ($response->getHttpCode() == 200) {
          return json_decode($response->getData(), true);
        }
        break;
      case 'hqmc':
        $url = '/application?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=hqmc&multiple=1';
        $response = $this->apiCoreCommunication->sendGetRequest($url);
        if ($response->getHttpCode() == 200) {
          return json_decode($response->getData(), true);
        }
        break;
      default:
        return [];
    }
  }

  /**
   * @param User $user
   * @param bool $isDroite
   * @return array|mixed|null
   */
  public function getTopLevelModules(User $user, bool $isDroite)
  {
    $key = 'menu_'.($isDroite ? 1: 0);
    $keyMenuHash = 'menu_hash';
    $topLevelModules = $this->session->get($key);
    $roleModuleHash = $this->em->getRepository(ParametrageInterface::class)->findOneBy(['cle' => 'menu_hash']);

    if (!is_null($topLevelModules)) {
      if ($roleModuleHash instanceof ParametrageInterface){
        $currentMenuHash = $this->session->get($keyMenuHash);
        if ($currentMenuHash == $roleModuleHash->getValeur()){
          return json_decode($topLevelModules);
        }
      }
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
            'acces' => $roleModule->isAcces(),
            'type' => $module->getTypeApplication(),
            'apps' => $roleModule->getApps(),
            'data_application' => json_decode($module->getDataApplication()),
            'route' => $module->getRoute(),
            'children' => $this->getChildren($module, $role),
            'redirection_route' => $module->getRedirectionRoute()
          ];
        }
      }
    }


    if ($roleModuleHash instanceof ParametrageInterface){
      $this->session->set($key, json_encode($topLevelModules));
      // Pour gérer la suppression du cache dès qu'une modification est détectée
      $this->session->set($keyMenuHash, $roleModuleHash->getValeur());
    }
    return $topLevelModules;
  }

  public function getModuleNameByRoute(UserInterface $user, $routeName){
    $key = 'module_name_'.$routeName;
    $moduleName = $this->session->get($key);
    $keyMenuHash = $key.'_hash';
    $roleModuleHash = $this->em->getRepository(ParametrageInterface::class)->findOneBy(['cle' => 'menu_hash']);

    if (!is_null($moduleName)) {
      if ($roleModuleHash instanceof ParametrageInterface){
        $currentMenuHash = $this->session->get($keyMenuHash);
        if ($currentMenuHash == $roleModuleHash->getValeur()){
          return $moduleName;
        }
      }
    }
    $route = $this->em->getRepository(RouteInterface::class)->findOneBy(['route' => $routeName]);
    if ($route instanceof RouteInterface){
      $module = $this->em->getRepository(ModuleInterface::class)->findOneBy(['code' => $route->getCodeModule()]);
      if ($module instanceof ModuleInterface) {
        $role = $user->getRole();
        if ($role instanceof RoleInterface){
          $roleModule = $role->getRoleModule($module);
          if ($roleModule instanceof RoleModuleInterface){
            if ($roleModuleHash instanceof ParametrageInterface) {
              $this->session->set($key, $roleModule->getLibelle());
              $this->session->set($keyMenuHash, $roleModuleHash->getValeur());
            }
            return $roleModule->getLibelle();
          }
        }
      }
    }
    return '';
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
    $keyMenuHash = $key.'_hash';
    $roleModuleHash = $this->em->getRepository(ParametrageInterface::class)->findOneBy(['cle' => 'menu_hash']);

    if (!is_null($secondLevelModules)) {
      if ($roleModuleHash instanceof ParametrageInterface){
        $currentMenuHash = $this->session->get($keyMenuHash);
        if ($currentMenuHash == $roleModuleHash->getValeur()){
          return json_decode($secondLevelModules);
        }
      }
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
                'acces' => $roleModule->isAcces(),
                'type' => $moduleEnfant->getTypeApplication(),
                'data_application' => json_decode($moduleEnfant->getDataApplication()),
                'route' => $moduleEnfant->getRoute(),
                'children' => $this->getChildren($moduleEnfant, $role),
                'redirection_route' => $moduleEnfant->getRedirectionRoute()
              ];
            }
          }

          if ($roleModuleHash instanceof ParametrageInterface) {
            $this->session->set($key, json_encode($secondLevelModules));
            $this->session->set($keyMenuHash, $roleModuleHash->getValeur());
          }
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
          $roleModule = $this->em->getRepository(RoleModuleInterface::class)->findOneBy(['role' => $role, 'module' => $enfant]);
          if ($roleModule instanceof RoleModuleInterface){
            $children[] = [
              'id' => $enfant->getId(),
              'libelle' => $roleModule->getLibelle(),
              'acces' => $roleModule->isAcces(),
              'type' => $module->getTypeApplication(),
              'data_application' => json_decode($module->getDataApplication()),
              'route' => $enfant->getRoute(),
              'children' => $this->getChildren($enfant, $role)
            ];
          }
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

  public function getUrlUpdatePassword(){
    return $this->urlUpdatePassword;
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
    $url = '/application-hqmc?token='.$tokenCoreHashed.'&token_date'.$tokenCoreDate.'&client_traitement='.$this->clientTraitement;
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
      'token' => hash('sha256', $nowFormat.$this->apiCoreCommunication->getApiCoreToken()),
      'date' => $nowFormat
    ];
  }
}
