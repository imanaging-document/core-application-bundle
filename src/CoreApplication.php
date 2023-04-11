<?php

namespace Imanaging\CoreApplicationBundle;

use App\Entity\CoreSynchronisationAction;
use App\Entity\HierarchiePatrimoine;
use App\Entity\HierarchiePatrimoineType;
use App\Entity\Interlocuteur;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Imanaging\ApiCommunicationBundle\ApiCoreCommunication;
use Imanaging\CheckFormatBundle\Interfaces\ConfigurationImportAutomatiqueMappingInterface;
use Imanaging\CoreApplicationBundle\Interfaces\ContratInterface;
use Imanaging\CoreApplicationBundle\Interfaces\CoreSynchronisationActionInterface;
use Imanaging\CoreApplicationBundle\Interfaces\HierarchiePatrimoineInterface;
use Imanaging\CoreApplicationBundle\Interfaces\HierarchiePatrimoineTypeInterface;
use Imanaging\CoreApplicationBundle\Interfaces\InterlocuteurContratInterface;
use Imanaging\CoreApplicationBundle\Interfaces\InterlocuteurInterface;
use Imanaging\CoreApplicationBundle\Interfaces\InterlocuteurTypeInterface;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RouteInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Imanaging\ZeusUserBundle\Interfaces\ParametrageInterface;
use phpseclib3\Net\SFTP;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CoreApplication
{
  const STATUT_EXECUTION_EN_ATTENTE = 'en_attente';
  const STATUT_EXECUTION_EN_COURS = 'en_cours';
  const STATUT_EXECUTION_ERREUR = 'erreur';
  const STATUT_EXECUTION_TERMINE = 'termine';
  const STATUT_EXECUTION_PASSEE = 'passee';
  const TYPE_TRAITEMENT_AUTOMATIQUE_CONFIGURATION_MAPPING = 'configuration-mapping';
  const TYPE_TRAITEMENT_AUTOMATIQUE_CONFIGURATION_EXPORT = 'configuration-export';
  const TYPE_TRAITEMENT_AUTOMATIQUE_WEBHOOK = 'webhook';
  private $em;
  private $apiCoreCommunication;
  private $basePath;
  private $requestStack;
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
   * @param RequestStack $requestStack
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
  public function __construct(EntityManagerInterface $em, ApiCoreCommunication $apiCoreCommunication, RequestStack $requestStack,
                                                     $basePath, $urlLogout, $urlProfile, $urlHomepage, $appSecret, $appName, $ownUrl, $ownUrlApi,
                                                     $clientTraitement, $traitementYear, $coreApiType, $urlUpdatePassword)
  {
    $this->em = $em;
    $this->apiCoreCommunication = $apiCoreCommunication;
    $this->requestStack = $requestStack;
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
   * Ajout d'un nouveau segment
   */
  public function synchroniserHierarchiePatrimoine(CoreSynchronisationActionInterface $synchronisationAction, $output = null){
    $synchronisationActionId = $synchronisationAction->getId();
    $startTime = microtime(true);
    $synchronisationAction->setDateLancement(new \DateTime());
    $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_COURS);
    $this->em->persist($synchronisationAction);
    $this->em->flush();

    $token = hash('sha256', $this->apiCoreCommunication->getApiCoreToken());
    // Etape 1 : On synchronise les types de hierarchie
    if ($output instanceof OutputInterface) {
      $output->writeln('Etape 1 : On synchronise les types de hierarchie');
    }
    $typesToRemove = [];
    $types = $this->em->getRepository(HierarchiePatrimoineTypeInterface::class)->findAll();
    foreach ($types as $type) {
      $typesToRemove[$type->getLibelle()] = $type;
    }

    $resTypes = $this->apiCoreCommunication->sendGetRequest('/patrimoine-hierarchie/types?token='.$token);
    if ($resTypes->getHttpCode() == 200) {
      $dataTypes = json_decode($resTypes->getData(), true);
      $dataType = $dataTypes;
      $parent = null;
      $niveau = 0;
      while(isset($dataType['libelle'])) {
        $niveau++;
        if (array_key_exists($dataType['libelle'], $typesToRemove)) {
          $type = $typesToRemove[$dataType['libelle']];
          unset($typesToRemove[$dataType['libelle']]);
        } else {
          $className = $this->em->getRepository(HierarchiePatrimoineTypeInterface::class)->getClassName();
          $type = new $className();
        }
        if ($type instanceof HierarchiePatrimoineTypeInterface) {
          $type->setLibelle($dataType['libelle']);
          $type->setParent($parent);
          $type->setNiveau($niveau);
          $this->em->persist($type);
          $parent = $type;
        }
        $dataType = $dataType['enfant'];
      }
      $this->em->flush();
      $this->em->clear();
      if ($output instanceof OutputInterface) {
        $output->writeln('Etape 1 : Fin de la synchronisation des ' . $this->em->getRepository(HierarchiePatrimoineTypeInterface::class)->count([]) . ' types');
        $output->writeln('Etape 2 : On synchronise la hierarchie');
      }

      $dataType = $dataTypes;
      $indexType = 0;
      while(isset($dataType['libelle'])) {
        $offset = 0;
        $limit = 100;
        $libelleType = $dataType['libelle'];
        $indexType++;
        $nbType = $dataType['nb'];
        if ($output instanceof OutputInterface) {
          $output->writeln('Synchronisation du ' . $indexType. ' - ' . $libelleType);
          $pb = new ProgressBar($output, $nbType);
          $pb->start();
        }

        $type = $this->em->getRepository(HierarchiePatrimoineTypeInterface::class)->findOneBy(['libelle' => $libelleType]);
        if (!($type instanceof HierarchiePatrimoineTypeInterface)) {
          $errorMessage = '#1 Impossible de récupérer le type : ' . $libelleType;
          $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
          $synchronisationAction->setDateFin(new \DateTime());
          $synchronisationAction->setDuree($duree);
          $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_ERREUR);
          $synchronisationAction->setErrorMessage($errorMessage);
          $this->em->persist($synchronisationAction);
          $this->em->flush();
          return ['success' => false, 'error_message' => $errorMessage];
        }
        $hierarchiesPatrimoines = $this->em->getRepository(HierarchiePatrimoineInterface::class)->findBy(['type' => $type]);
        $hierarchiesPatrimoineToRemove = [];
        foreach ($hierarchiesPatrimoines as $hpType) {
          if ($hpType instanceof HierarchiePatrimoineInterface) {
            if ($hpType->getParent() instanceof HierarchiePatrimoineInterface) {
              $hierarchiesPatrimoineToRemove[$hpType->getCode().'_'.$hpType->getParent()->getCode()] = $hpType;
            } else {
              $hierarchiesPatrimoineToRemove[$hpType->getCode().'_'] = $hpType->getId();
            }
          }
        }

        while ($offset < $nbType) {
          $type = $this->em->getRepository(HierarchiePatrimoineTypeInterface::class)->findOneBy(['libelle' => $libelleType]);
          if (!($type instanceof HierarchiePatrimoineTypeInterface)) {
            $errorMessage = '#2 Impossible de récupérer le type : ' . $libelleType;
            $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
            $synchronisationAction->setDateFin(new \DateTime());
            $synchronisationAction->setDuree($duree);
            $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_ERREUR);
            $synchronisationAction->setErrorMessage($errorMessage);
            $this->em->persist($synchronisationAction);
            $this->em->flush();
            return ['success' => false, 'error_message' => $errorMessage];
          }

          $hierarchiesPatrimoineExistants = [];
          $hierarchiesPatrimoines = $this->em->getRepository(HierarchiePatrimoineInterface::class)->findBy(['type' => $type]);
          foreach ($hierarchiesPatrimoines as $hpType) {
            if ($hpType instanceof HierarchiePatrimoineInterface) {
              if ($hpType->getParent() instanceof HierarchiePatrimoineInterface) {
                $hierarchiesPatrimoineExistants[$hpType->getCode().'_'.$hpType->getParent()->getCode()] = $hpType;
              } else {
                $hierarchiesPatrimoineExistants[$hpType->getCode().'_'] = $hpType;
              }
            }
          }
          $parents = [];
          $retry = 0;
          $continue = false;
          do {
            $resHierarchie = $this->apiCoreCommunication->sendGetRequest('/patrimoine-hierarchie?token='.$token.'&type=' .
              $type->getLibelle() . '&offset=' . $offset . '&limit=' . $limit);
            if ($resHierarchie->getHttpCode() == 200) {
              $hierarchiesData = json_decode($resHierarchie->getData(), true);
              foreach ($hierarchiesData as $hierarchieData) {
                $key = $hierarchieData['code'].'_'.$hierarchieData['parent_code'];
                if ($hierarchieData['parent_code'] != '') {
                  if (!array_key_exists($hierarchieData['parent_code'], $parents)) {
                    $parents[$hierarchieData['parent_code']] = $this->em->getRepository(HierarchiePatrimoineInterface::class)->findOneBy(
                      [
                        'type' => $this->em->getRepository(HierarchiePatrimoineTypeInterface::class)->findOneBy(['libelle' => $hierarchieData['parent_type']]),
                        'code' => $hierarchieData['parent_code']
                      ]);
                  }
                  $parent = $parents[$hierarchieData['parent_code']];
                } else {
                  $parent = null;
                }

                if (array_key_exists($key, $hierarchiesPatrimoineExistants)) {
                  $hierarchiePatrimoine = $hierarchiesPatrimoineExistants[$key];
                  unset($hierarchiesPatrimoineToRemove[$key]);
                } else {
                  $className = $this->em->getRepository(HierarchiePatrimoineInterface::class)->getClassName();
                  $hierarchiePatrimoine = new $className();
                }
                if ($hierarchiePatrimoine instanceof HierarchiePatrimoineInterface) {
                  $hierarchiePatrimoine->setCode($hierarchieData['code']);
                  $hierarchiePatrimoine->setLibelle($hierarchieData['libelle']);
                  $hierarchiePatrimoine->setType($type);
                  $hierarchiePatrimoine->setParent($parent);
                  $hierarchiePatrimoine->setNbPatrimoines($hierarchieData['nb']);
                  $this->em->persist($hierarchiePatrimoine);
                }
              }
              $continue = true;
              if ($output instanceof OutputInterface) {
                $pb->advance(count($hierarchiesData));
              }
            }
            $retry++;
          } while (!$continue && $retry < 5);

          if (!$continue) {
            $errorMessage = 'Une erreur est survenue lors de la récupération de la hierarchie depuis le CORE : '.$resTypes->getHttpCode();
            $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
            $synchronisationAction->setDateFin(new \DateTime());
            $synchronisationAction->setDuree($duree);
            $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_ERREUR);
            $synchronisationAction->setErrorMessage($errorMessage);
            $this->em->persist($synchronisationAction);
            $this->em->flush();
            return ['success' => false, 'error_message' => $errorMessage];
          }

          $offset += $limit;

          $this->em->flush();
          $this->em->clear();
        }

        if ($output instanceof OutputInterface) {
          $pb->finish();
          $output->writeln('');
        }

        $nbRemove = 0;
        // on remove tous les hierarchiepatrimoinetoremove
        foreach ($hierarchiesPatrimoineToRemove as $hierarchieId) {
          $this->em->remove($this->em->getRepository(HierarchiePatrimoine::class)->find($hierarchieId));
          $nbRemove++;
        }
        $this->em->flush();

        if ($output instanceof OutputInterface && $nbRemove > 0) {
          $pb->finish();
          $output->writeln($nbRemove . ' hierarchies historiques supprimées');
        }
        $dataType = $dataType['enfant'];
      }

      if ($output instanceof OutputInterface) {
        $output->writeln('Etape 2 : Terminée');
        $output->writeln('');
        $output->writeln('Etape 3 : Synchronisation des codes enquêtes');
      }
      $className = $this->em->getRepository(ContratInterface::class)->getClassName();
      $dql = 'UPDATE ' . $className . ' c SET c.hierarchiePatrimoine = null';
      $this->em->createQuery($dql)->execute();

      $lastType = $this->em->getRepository(HierarchiePatrimoineType::class)->findOneBy(['libelle' => $libelleType]);
      if ($lastType instanceof HierarchiePatrimoineType) {
        $nbHierarchiePatrimoineNiveauMin = $this->em->getRepository(HierarchiePatrimoineInterface::class)->count(['type' => $lastType]);
        $offset = 0;
        $limit = 100;
        $limitDetail = 100;

        $className = $this->em->getRepository(HierarchiePatrimoineInterface::class)->getClassName();
        $dql = 'SELECT sum(c.nbPatrimoines) FROM ' . $className . ' c where c.type = :type';
        $nbPatrimoinesTotal = $this->em->createQuery($dql)->setParameter('type', $lastType)->getSingleScalarResult();

        if ($output instanceof OutputInterface) {
          $pb = new ProgressBar($output, $nbPatrimoinesTotal);
          $pb->start();
        }

        while ($offset < $nbHierarchiePatrimoineNiveauMin) {
          $lastType = $this->em->getRepository(HierarchiePatrimoineType::class)->findOneBy(['libelle' => $libelleType]);
          $hierarchiesPatrimoines = $this->em->getRepository(HierarchiePatrimoineInterface::class)->findBy(['type' => $lastType], ['id' => 'ASC'], $limit, $offset);
          foreach ($hierarchiesPatrimoines as $hierarchiePatrimoine) {
            if ($hierarchiePatrimoine instanceof HierarchiePatrimoine) {
              $offsetDetail = 0;
              $codeParent = ($hierarchiePatrimoine->getParent() instanceof HierarchiePatrimoine) ? $hierarchiePatrimoine->getParent()->getCode() : '';
              while ($offsetDetail < $hierarchiePatrimoine->getNbPatrimoines()) {
                $resCodesEnquetes = $this->apiCoreCommunication->sendGetRequest(
                  '/patrimoine-hierarchie/codes-enquetes?token='.$token.'&type=' . $lastType->getLibelle() .
                  '&code_hierarchie=' . $hierarchiePatrimoine->getCode()  . '&code_parent=' . $codeParent .
                  '&offset=' . $offsetDetail . '&limit=' . $limitDetail);
                if ($resCodesEnquetes->getHttpCode() == 200) {
                  $codesEnquetes = json_decode($resCodesEnquetes->getData(), true);
                  $className = $this->em->getRepository(ContratInterface::class)->getClassName();
                  $dql = 'UPDATE ' . $className . ' c SET c.hierarchiePatrimoine = ' . $hierarchiePatrimoine->getId() . ' where c.codeEnquete in (:codesEnquete)';
                  $this->em->createQuery($dql)->setParameter('codesEnquete', $codesEnquetes)->execute();
                } else {
                  return ['success' => false, 'error_message' => 'Une erreur est survenue lors de la récupération des codes enquêtes. Code HTTP : ' .
                    $resCodesEnquetes->getHttpCode()];
                }
                $offsetDetail += $limitDetail;
              }
              if ($output instanceof OutputInterface) {
                $pb->advance($hierarchiePatrimoine->getNbPatrimoines());
              }
            }
          }
          $offset += $limit;
        }
        if ($output instanceof OutputInterface) {
          $pb->finish();
        }

        $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
        $synchronisationAction->setDateFin(new \DateTime());
        $synchronisationAction->setDuree($duree);
        $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_TERMINE);
        $this->em->persist($synchronisationAction);
        $this->em->flush();

        return ['success' => true];
      }
    } else {
      $errorMessage = 'Une erreur est survenue lors de la récupération des types de hierarchie depuis le CORE : '.$resTypes->getHttpCode();
      $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
      $synchronisationAction->setDateFin(new \DateTime());
      $synchronisationAction->setDuree($duree);
      $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_ERREUR);
      $synchronisationAction->setErrorMessage($errorMessage);
      $this->em->persist($synchronisationAction);
      $this->em->flush();
      return ['success' => false,
        'error_message' => $errorMessage];
    }
  }

  public function synchronisationInterlocuteurs(CoreSynchronisationActionInterface $synchronisationAction, $output = null): array
  {
    $synchronisationActionId = $synchronisationAction->getId();
    $startTime = microtime(true);
    $synchronisationAction->setDateLancement(new \DateTime());
    $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_COURS);
    $this->em->persist($synchronisationAction);
    $this->em->flush();

    $token = hash('sha256', $this->apiCoreCommunication->getApiCoreToken());
    // Etape 1 : On synchronise les types d'interlocuteurs
    if ($output instanceof OutputInterface) {
      $output->writeln('Etape 1 : On synchronise les types d\'interlocuteurs');
    }
    $typesToRemove = [];
    $types = $this->em->getRepository(InterlocuteurTypeInterface::class)->findAll();
    foreach ($types as $type) {
      $typesToRemove[$type->getIdCore()] = $type;
    }

    $resTypes = $this->apiCoreCommunication->sendGetRequest('/interlocuteur/types/find-all?token='.$token);
    if ($resTypes->getHttpCode() == 200) {
      $coreTypes = json_decode($resTypes->getData(), true);
      foreach ($coreTypes as $groupeLabel => $coreGroupeType) {
        foreach ($coreGroupeType['types'] as $coreType) {
          $typeTmp = null;
          if (array_key_exists($coreType['id'], $typesToRemove)) {
            $typeTmp = $typesToRemove[$coreType['id']];
            unset($typesToRemove[$coreType['id']]);
          } else {
            $className = $this->em->getRepository(InterlocuteurTypeInterface::class)->getClassName();
            $typeTmp = new $className();
            $typeTmp->setIdCore($coreType['id']);
          }
          $typeTmp->setGroupe($groupeLabel);
          $typeTmp->setLibelle($coreType['libelle']);
          $this->em->persist($typeTmp);
        }
      }
      $this->em->flush();
      foreach ($typesToRemove as $typeToRemove) {
        $this->em->remove($typeToRemove);
      }
      $this->em->flush();
      $this->em->clear();

      if ($output instanceof OutputInterface) {
        $output->writeln('Etape 2 : On synchronise les interlocuteurs');
      }

      $className = $this->em->getRepository(InterlocuteurContratInterface::class)->getClassName();
      $dql = 'DELETE FROM ' . $className;
      $this->em->createQuery($dql)->execute();

      $nbTypes = $this->em->getRepository(InterlocuteurTypeInterface::class)->count([]);
      if ($output instanceof OutputInterface) {
        $pb = new ProgressBar($output, $nbTypes);
        $pb->start();
      }
      for ($i = 0; $i <= $nbTypes; $i++) {
        $types = $this->em->getRepository(InterlocuteurTypeInterface::class)->findBy([], ['id' => 'ASC'], 1, $i);
        foreach ($types as $type){
          if ($type instanceof InterlocuteurTypeInterface) {
            // on récupère via API les interlocuteurs
            $resTypeDetail = $this->apiCoreCommunication->sendGetRequest('/interlocuteur/type/'.$type->getIdCore().'/find-all?token='.$token);
            if ($resTypeDetail->getHttpCode() == 200) {
              // GESTION DES INTERLOCUTEURS A SUPPRIMER
              $interlocuteursToRemove = [];
              $interlocuteursLocal = $this->em->getRepository(InterlocuteurInterface::class)->findBy(['type' => $type], []);
              foreach ($interlocuteursLocal as $interlocuteurLocal) {
                $interlocuteursToRemove[$interlocuteurLocal->getIdCore()] = $interlocuteurLocal->getId();
              }

              foreach (json_decode($resTypeDetail->getData(), true)['interlocuteurs'] as $interlocuteurCore){
                if (array_key_exists($interlocuteurCore['id_core'], $interlocuteursToRemove)) {
                  $interlocuteur = $this->em->getRepository(InterlocuteurInterface::class)->find($interlocuteursToRemove[$interlocuteurCore['id_core']]);
                  unset($interlocuteursToRemove[$interlocuteurCore['id_core']]);
                } else {
                  $className = $this->em->getRepository(InterlocuteurInterface::class)->getClassName();
                  $interlocuteur = new $className();
                  $interlocuteur->setIdCore($interlocuteurCore['id_core']);
                  $interlocuteur->setType($type);
                }
                $interlocuteur->setLibelle($interlocuteurCore['libelle']);

                if ($interlocuteurCore['user'] != '') {
                  $user = $this->em->getRepository(UserInterface::class)->findOneBy(['login' => $interlocuteurCore['user']]);
                  $interlocuteur->setUser($user);
                }
                $this->em->persist($interlocuteur);
                $this->em->flush();
                $currentInterlocuteurId = $interlocuteur->getId();

                // on récupère les patrimoines associés
                $moreCodesEnquete = true;
                $offset = 0;
                $limit = 200;

                while ($moreCodesEnquete) {
                  $interlocuteur = $this->em->getRepository(InterlocuteurInterface::class)->find($currentInterlocuteurId);
                  $resCodesEnquetes = $this->apiCoreCommunication->sendGetRequest(
                    '/interlocuteur/'.$interlocuteur->getIdCore().'/patrimoines?token='.$token.'&offset='.$offset.'&limit='.$limit);
                  if ($resCodesEnquetes->getHttpCode() == 200) {
                    $dataDecoded = json_decode($resCodesEnquetes->getData(), true);
                    $contrats = $this->em->getRepository(ContratInterface::class)->findBy(['codeEnquete' => $dataDecoded['codes_enquetes']]);
                    $className = $this->em->getRepository(InterlocuteurContratInterface::class)->getClassName();
                    foreach ($contrats as $contrat) {
                      $interlocuteurContrat = new $className();
                      $interlocuteurContrat->setContrat($contrat);
                      $interlocuteurContrat->setInterlocuteur($interlocuteur);
                      $this->em->persist($interlocuteurContrat);
                    }
                    $moreCodesEnquete = $dataDecoded['more'];
                  } else {
                    $duree = microtime(true) - $startTime;
                    $errorMessage = 'Une erreur est survenue lors de la récupération des codes enquêtees depuis le core. Interlocuteur . ' .
                      $interlocuteur->getLibelle() . '. HTTP : ' . $resCodesEnquetes->getHttpCode();

                    $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
                    $synchronisationAction->setDateFin(new \DateTime());
                    $synchronisationAction->setDuree($duree);
                    $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_ERREUR);
                    $synchronisationAction->setErrorMessage($errorMessage);
                    $this->em->persist($synchronisationAction);
                    $this->em->flush();
                    return ['success' => false, 'duree' => $duree,
                      'error_message' => $errorMessage];
                  }
                  $offset += $limit;
                  $this->em->flush();
                  $this->em->clear();
                }
              }

              foreach ($interlocuteursToRemove as $item) {
                $interlocuteurToRm = $this->em->getRepository(InterlocuteurInterface::class)->find($item);
                if ($interlocuteurToRm instanceof InterlocuteurInterface){
                  $this->em->remove($interlocuteurToRm);
                }
              }
              $this->em->flush();
            } else {
              $duree = microtime(true) - $startTime;
              $errorMessage = 'Une erreur est survenue lors de la récupération des interlocuteurs depuis le core. HTTP : ' . $resTypeDetail->getHttpCode();

              $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
              $synchronisationAction->setDateFin(new \DateTime());
              $synchronisationAction->setDuree($duree);
              $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_ERREUR);
              $synchronisationAction->setErrorMessage($errorMessage);
              $this->em->persist($synchronisationAction);
              $this->em->flush();
              return [
                'success' => false,
                'duree' => $duree,
                'error_message' => $errorMessage
              ];
            }
          } else {
            $duree = microtime(true) - $startTime;
            $errorMessage = 'Erreur inconnue #1';

            $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
            $synchronisationAction->setDateFin(new \DateTime());
            $synchronisationAction->setDuree($duree);
            $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_ERREUR);
            $synchronisationAction->setErrorMessage($errorMessage);
            $this->em->persist($synchronisationAction);
            $this->em->flush();

            return [
              'success' => false,
              'duree' => $duree,
              'error_message' => $errorMessage
            ];
          }
        }
        if ($output instanceof OutputInterface) {
          $pb->advance();
        }
        $this->em->clear();
      }

      if ($output instanceof OutputInterface) {
        $pb->finish();
      }

      $duree = microtime(true) - $startTime;

      $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
      $synchronisationAction->setDateFin(new \DateTime());
      $synchronisationAction->setDuree($duree);
      $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_TERMINE);
      $this->em->persist($synchronisationAction);
      $this->em->flush();

      return ['success' => true, 'duree' => $duree];
    } else {
      $errorMessage = 'Une erreur est survenue lors de la récupération des types interlocuteurs depuis le core. HTTP : ' . $resTypes->getHttpCode();
      $synchronisationAction = $this->em->getRepository(CoreSynchronisationActionInterface::class)->find($synchronisationActionId);
      $synchronisationAction->setDateFin(new \DateTime());
      $synchronisationAction->setDuree($duree);
      $synchronisationAction->setStatut(CoreSynchronisationActionInterface::STATUT_EN_ERREUR);
      $synchronisationAction->setErrorMessage($errorMessage);
      $this->em->persist($synchronisationAction);
      $this->em->flush();
      $duree = microtime(true) - $startTime;
      return ['success' => false, 'duree' => $duree,
        'error_message' => $errorMessage];
    }
  }

  /**
   * @param User $user
   * @return array
   */
  public function getMenuApplications(User $user)
  {
    $keyMenu = 'menu_applications';
    $applications = $this->requestStack->getSession()->get($keyMenu);
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
        $this->requestStack->getSession()->set($keyMenu, json_encode($applications));
      } else {
        $applications = [];
      }
    } else {
      $applications = [];
    }

    return $applications;
  }

  /**
   * @param User $user
   * @return array
   */
  public function getApplicationsListing(User $user)
  {
    $keyMenu = 'list_applications';
    $applications = $this->requestStack->getSession()->get($keyMenu);
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
      $url = '/applications-all/utilisateur?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&application='.$applicationId.'&login='.$user->getLogin();
      $resultRequest = $this->apiCoreCommunication->sendGetRequest($url);
      if ($resultRequest->getHttpCode() == 200) {
        $typesApplication = json_decode($resultRequest->getData(), true);
        $this->requestStack->getSession()->set($keyMenu, json_encode($typesApplication));
      } else {
        $typesApplication = [];
      }
    } else {
      $typesApplication = [];
    }

    return $typesApplication;
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

  public function getAllApplicationsForList($type){
    $tokenAndDate = $this->getCoreTokenAndDate();
    $tokenCoreHashed = $tokenAndDate['token'];
    $tokenCoreDate = $tokenAndDate['date'];

    switch ($type) {
      case 'dossier_locataire':
        $url = '/applications-all/utilisateur?token='.$tokenCoreHashed.'&token_date='.$tokenCoreDate.'&type_application=dossier-locataire&multiple=1';
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
    $topLevelModules = $this->requestStack->getSession()->get($key);
    $roleModuleHash = $this->em->getRepository(ParametrageInterface::class)->findOneBy(['cle' => 'menu_hash']);

    if (!is_null($topLevelModules)) {
      if ($roleModuleHash instanceof ParametrageInterface){
        $currentMenuHash = $this->requestStack->getSession()->get($keyMenuHash);
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
      $this->requestStack->getSession()->set($key, json_encode($topLevelModules));
      // Pour gérer la suppression du cache dès qu'une modification est détectée
      $this->requestStack->getSession()->set($keyMenuHash, $roleModuleHash->getValeur());
    }
    return $topLevelModules;
  }

  public function getModuleNameByRoute(UserInterface $user, $routeName){
    $key = 'module_name_'.$routeName;
    $moduleName = $this->requestStack->getSession()->get($key);
    $keyMenuHash = $key.'_hash';
    $roleModuleHash = $this->em->getRepository(ParametrageInterface::class)->findOneBy(['cle' => 'menu_hash']);

    if (!is_null($moduleName)) {
      if ($roleModuleHash instanceof ParametrageInterface){
        $currentMenuHash = $this->requestStack->getSession()->get($keyMenuHash);
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
              $this->requestStack->getSession()->set($key, $roleModule->getLibelle());
              $this->requestStack->getSession()->set($keyMenuHash, $roleModuleHash->getValeur());
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
    $secondLevelModules = $this->requestStack->getSession()->get($key);
    $keyMenuHash = $key.'_hash';
    $roleModuleHash = $this->em->getRepository(ParametrageInterface::class)->findOneBy(['cle' => 'menu_hash']);

    if (!is_null($secondLevelModules)) {
      if ($roleModuleHash instanceof ParametrageInterface){
        $currentMenuHash = $this->requestStack->getSession()->get($keyMenuHash);
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
            $this->requestStack->getSession()->set($key, json_encode($secondLevelModules));
            $this->requestStack->getSession()->set($keyMenuHash, $roleModuleHash->getValeur());
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

  public function getNbContratsConcernes(HierarchiePatrimoineInterface $hierarchiePatrimoine)
  {
    $nbContrats = 0;
    $enfants = $hierarchiePatrimoine->getEnfants();
    if (count($enfants) > 0) {
      foreach ($enfants as $enfant) {
        $nbContrats += $this->getNbContratsConcernes($enfant);
      }

      return $nbContrats;
    } else {
      $className = $this->em->getRepository(ContratInterface::class)->getClassName();
      $dql = 'SELECT count(c.id) nbContrats FROM ' . $className . ' c WHERE c.hierarchiePatrimoine = :hp';
      return $this->em->createQuery($dql)->setParameter('hp', $hierarchiePatrimoine)->getSingleScalarResult();
    }
  }

  public function createAction(string $typeSynchronisation)
  {
    $actionEnAttente = $this->em->getRepository(CoreSynchronisationActionInterface::class)->findBy([
        'typeSynchronisation' => $typeSynchronisation,
        'statut' => CoreSynchronisationActionInterface::STATUT_EN_ATTENTE
      ]);

    if (count($actionEnAttente) == 0) {
      $className = $this->em->getRepository(CoreSynchronisationActionInterface::class)->getClassName();
      $action = new $className();
      if ($action instanceof CoreSynchronisationActionInterface) {
        $action->setDateCreation(new \DateTime());
        $action->setStatut(CoreSynchronisationActionInterface::STATUT_EN_ATTENTE);
        $action->setTypeSynchronisation($typeSynchronisation);
        $this->em->persist($action);
        $this->em->flush();

        return ['success' => true, 'synchronisation_action' => $action];
      } else {
        throw new \Exception('Impossible de créer une action de synchronisation');
      }
    } else {
      return ['success' => false, 'error_message' => 'Déjà une action en attente'];
    }
  }

  public function getActionEnAttente(string $typeSynchronisation)
  {
    return $this->em->getRepository(CoreSynchronisationActionInterface::class)->findOneBy([
        'typeSynchronisation' => $typeSynchronisation,
        'statut' => CoreSynchronisationActionInterface::STATUT_EN_ATTENTE
      ]);
  }

  public function getContratsIdsByUser(UserInterface $user)
  {
    $interlocuteurs = $this->em->getRepository(InterlocuteurInterface::class)->findBy(['user' => $user]);
    if (count($interlocuteurs) > 0) {
      foreach ($interlocuteurs as $interlocuteur) {
        if ($interlocuteur instanceof InterlocuteurInterface) {
          $interlocuteursId[] = $interlocuteur->getId();
        }
      }
      return $this->em->getRepository(InterlocuteurContratInterface::class)->getContratsId($interlocuteursId);
    }

    return null;
  }

  public function updateEtatExecutionImportAutomatique($executionIdCore, $statut, $message, $dataSuivi = []): bool
  {
    $postData = [
      'token' => hash('sha256', $this->apiCoreCommunication->getApiCoreToken()),
      'execution_id' => $executionIdCore,
      'statut' => $statut,
      'message' => $message,
      'data_suivi' => json_encode($dataSuivi)
    ];
    // on fait un retour en erreur
    $res = $this->apiCoreCommunication->sendPostRequest('/import-automatique/execution-result', $postData);
    return ($res->getHttpCode() == '200');
  }

  public function copyFromSftp(ConfigurationImportAutomatiqueMappingInterface $importAutomatique, $projectDir)
  {
    $files = [];
    try {
      // On récupère les fichiers sur le SFTP que l'on dépose dans le dossier associé
      $sftp = new SFTP($importAutomatique->getSftpUrl(), $importAutomatique->getSftpPort());
      if (!$sftp->login($importAutomatique->getSftpLogin(), $importAutomatique->getSftpPassword())) {
        return [
          'success' => false,
          'continue' => false,
          'error_message' => $importAutomatique->getMappingConfiguration()->getLibelle() . ' : la connexion SFTP a échoué.'
        ];
      }
    } catch (Exception $e) {
      return [
        'success' => false,
        'continue' => false,
        'error_message' => $importAutomatique->getMappingConfiguration()->getLibelle() . ' : la connexion SFTP a échoué. ' . $e->getMessage()
      ];
    }

    $sftp->mkdir($importAutomatique->getCheminRepertoire(), 0700, true);
    if (!$sftp->is_dir($importAutomatique->getCheminRepertoire())) {
      return [
        'success' => false,
        'continue' => false,
        'error_message' => $importAutomatique->getMappingConfiguration()->getLibelle() . ' : Le répertoire de cet import automatique n\'existe pas sur le serveur SFTP.'
      ];
    }

    // On regarde s'il existe des fichiers dans ce répertoire à intégrer
    $fichiersDistants = $sftp->rawlist($importAutomatique->getCheminRepertoire());
    $nbFichiersDistants = 0;
    foreach ($fichiersDistants as $fichierDistant => $infos) {
      if (!in_array($fichierDistant, ['.', '..', 'archive'])) {
        $nbFichiersDistants++;
      }
    }

    if ($nbFichiersDistants == 0) {
      if ($importAutomatique->isObligatoire()) {
        return [
          'success' => false,
          'continue' => false,
          'error_message' => $importAutomatique->getMappingConfiguration()->getLibelle() . ' : Aucun fichier en attente sur le serveur.'
        ];
      } else {
        return [
          'success' => true,
          'continue' => false,
          'error_message' => $importAutomatique->getMappingConfiguration()->getLibelle() . ' : Aucun fichier en attente sur le serveur.'
        ];
      }
    }

    if ($nbFichiersDistants > 1) {
      return [
        'success' => false,
        'continue' => false,
        'error_message' => $importAutomatique->getMappingConfiguration()->getLibelle() . ' : Trop de fichiers sont en attente sur le serveur ('
          . (count($fichiersDistants) - 2) . ').'
      ];
    }

    $expectedLocalFilepath = $importAutomatique->getMappingConfiguration()->getType()->getFilesDirectory();
    $expectedLocalFilename = $importAutomatique->getMappingConfiguration()->getType()->getFilename();
    $cheminRepertoireImportAutomatique = $importAutomatique->getCheminRepertoire();

    foreach ($fichiersDistants as $fichierDistant => $infos) {
      if (!in_array($fichierDistant, ['.', '..', 'archive'])) {

        if (!is_dir($projectDir . $expectedLocalFilepath)) {
          mkdir($projectDir . $expectedLocalFilepath, 0755, true);
        }

        $now = new DateTime();

        $pathFichierDistant = $cheminRepertoireImportAutomatique . '/' . $fichierDistant;
        $extensionFichierDistant = pathinfo($pathFichierDistant)['extension'];

        $finalFilename = $expectedLocalFilename . '_' . $now->format('YmdHis') . '.' . $extensionFichierDistant;
        $fullFilepath = $projectDir . $expectedLocalFilepath . $finalFilename;

        $res = $sftp->get($pathFichierDistant, $fullFilepath);
        if (!$res) {
          return [
            'success' => false,
            'continue' => false,
            'error_message' => $importAutomatique->getMappingConfiguration()->getLibelle()
              . ' : Une erreur est survenue lors du téléchargement du fichier (' . $pathFichierDistant . ')'
          ];
        }
        // On archive ce fichier distant
        $sftp->mkdir($importAutomatique->getCheminRepertoire() . '/archive', 0700, true);
        $archivedPathFichierDistant = $importAutomatique->getCheminRepertoire() . '/archive/' . $now->format('YmdHis') . '_' . $fichierDistant;
        $sftp->rename($pathFichierDistant, $archivedPathFichierDistant);
        // Le fichier a bien été archivé
        $files[] = [
          'fichier_distant' => $fichierDistant,
          'final_filename' => $finalFilename,
          'date' => $now
        ];
      }
    }

    return ['success' => true, 'continue' => true, 'files' => $files];
  }
}
