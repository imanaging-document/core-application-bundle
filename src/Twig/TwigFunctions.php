<?php

namespace Imanaging\CoreApplicationBundle\Twig;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigFunctions extends AbstractExtension
{
  private $coreService;

  public function __construct(CoreApplication $coreService)
  {
    $this->coreService = $coreService;
  }
  public function getFunctions()
  {
    return array(
      new TwigFunction('getApplications', array($this, 'getApplications')),
      new TwigFunction('getTopLevelModules', [$this, 'getTopLevelModules']),
      new TwigFunction('getModulesLevel2ByRoute', [$this, 'getModulesLevel2ByRoute']),
      new TwigFunction('getApplicationInformation', [$this, 'getApplicationInformation']),
      new TwigFunction('getUrlLogout', [$this, 'getUrlLogout']),
      new TwigFunction('getUrlProfile', [$this, 'getUrlProfile']),
      new TwigFunction('getUrlHomepage', [$this, 'getUrlHomepage']),
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
   * @param $user
   * @return array
   */
  public function getApplicationInformation($moduleId){
    return $this->coreService->getApplicationInformation($moduleId);
  }

  /**
   * @param User $user
   * @param bool $isDroite
   * @return array|mixed|null
   */
  public function getTopLevelModules(UserInterface $user, bool $isDroite)
  {
    return $this->coreService->getTopLevelModules($user, $isDroite);
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
}
