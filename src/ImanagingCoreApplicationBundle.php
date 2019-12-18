<?php


namespace Imanaging\CoreApplicationBundle;


use Imanaging\CoreApplicationBundle\DependencyInjection\ImanagingCoreApplicationExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ImanagingCoreApplicationBundle extends Bundle
{
  /**
   * Overridden to allow for the custom extension alias.
   */
  public function getContainerExtension()
  {
    if (null === $this->extension) {
      $this->extension = new ImanagingCoreApplicationExtension();
    }
    return $this->extension;
  }
}