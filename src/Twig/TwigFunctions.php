<?php

namespace Imanaging\CoreApplicationBundle\Twig;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigFunctions extends AbstractExtension
{

  public function __construct(private readonly CoreApplication $coreService, private readonly UrlGeneratorInterface $generator, private readonly RequestStack $requestStack)
  {
  }

  public function getFunctions() : array
  {
    return array(
      new TwigFunction('getApplications', array($this, 'getApplications')),
      new TwigFunction('getTopLevelModules', [$this, 'getTopLevelModules']),
      new TwigFunction('getModuleNameByRoute', [$this, 'getModuleNameByRoute']),
      new TwigFunction('getModulesLevel2ByRoute', [$this, 'getModulesLevel2ByRoute']),
      new TwigFunction('getApplicationInformation', [$this, 'getApplicationInformation']),
      new TwigFunction('getUrlLogout', [$this, 'getUrlLogout']),
      new TwigFunction('getUrlProfile', [$this, 'getUrlProfile']),
      new TwigFunction('getUrlUpdatePassword', [$this, 'getUrlUpdatePassword']),
      new TwigFunction('getUrlHomepage', [$this, 'getUrlHomepage']),
      new TwigFunction('isRouteExiste', [$this, 'isRouteExiste']),
      new TwigFunction('getHierarchiesPatrimoines', [$this, 'getHierarchiesPatrimoines']),
      new TwigFunction('getInterlocuteurs', [$this, 'getInterlocuteurs']),
      new TwigFunction('gravatar', [$this, 'gravatar']),
      new TwigFunction('isConnectedAsOtherUser', [$this, 'isConnectedAsOtherUser']),
    );
  }

  /**
   * @param $user
   * @return array
   */
  public function getApplications($user){
    return $this->coreService->getMenuApplications($user);
  }

  /**
   * @param $moduleId
   * @param null $clientTraitement
   * @return array
   */
  public function getApplicationInformation($moduleId, $clientTraitement = null){
    return $this->coreService->getApplicationInformation($moduleId, $clientTraitement);
  }

  /**
   * @param UserInterface $user
   * @param bool $isDroite
   * @return array|mixed|null
   */
  public function getTopLevelModules(UserInterface $user, bool $isDroite)
  {
    return $this->coreService->getTopLevelModules($user, $isDroite);
  }

  public function getModuleNameByRoute(UserInterface $user, $route){
    return $this->coreService->getModuleNameByRoute($user, $route);
  }

  public function getModulesLevel2ByRoute(UserInterface $user, $route){
    return $this->coreService->getModulesLevel2ByRoute($user, $route);
  }

  public function getUrlLogout()
  {
    return $this->coreService->getUrlLogout();
  }

  public function getUrlProfile()
  {
    return $this->coreService->getUrlProfile();
  }

  public function getUrlHomepage()
  {
    return $this->coreService->getUrlHomepage();
  }

  public function getUrlUpdatePassword()
  {
    return $this->coreService->getUrlUpdatePassword();
  }

  /**
   * @param $dynamiqueRoute
   * @return bool
   */
  public function isRouteExiste($dynamiqueRoute)
  {
    try {
      $this->generator->generate($dynamiqueRoute);
      return true;
    } catch (RouteNotFoundException $e) {
      return false;
    }
  }

  /**
   * @param $dynamiqueRoute
   * @return bool
   */
  public function getHierarchiesPatrimoines($hpParentId = null)
  {
    return $this->coreService->getHierarchiesPatrimoines($hpParentId);
  }

  /**
   * @param $dynamiqueRoute
   * @return bool
   */
  public function getInterlocuteurs()
  {
    return $this->coreService->getInterlocuteurs();
  }

  /**
   * @param $mail
   * @return string
   */
  public function gravatar($mail): string
  {
    $url = 'https://www.gravatar.com/avatar/';
    $url .= md5( strtolower( trim( $mail ) ) );
    $url .= '?s=80&d=mp&r=g';
    return $url;
  }

  /**
   * @return bool
   */
  public function isConnectedAsOtherUser(): bool
  {
    try {
      $this->session = $this->requestStack->getSession();
    } catch (SessionNotFoundException $sessionNotFoundException) {
      return false;
    }
    return !is_null($this->session->get('zeus-connected-core'));
  }
}
