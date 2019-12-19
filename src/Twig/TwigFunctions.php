<?php

namespace Imanaging\CoreApplicationBundle\Twig;

use Imanaging\CoreApplicationBundle\CoreApplication;
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
    );
  }

  /**
   * @param $user
   * @return array
   */
  public function getApplications($user){
    return $this->coreService->getMenuApplications($user);
  }
}
