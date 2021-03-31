<?php
namespace Imanaging\CoreApplicationBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class ImanagingCoreApplicationExtension extends Extension
{
  /**
   * @param array $configs
   * @param ContainerBuilder $container
   * @throws Exception
   */
  public function load(array $configs, ContainerBuilder $container)
  {
    $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
    $loader->load('services.xml');

    $configuration = $this->getConfiguration($configs, $container);
    $config = $this->processConfiguration($configuration, $configs);

    $definition = $container->getDefinition('imanaging_core_application.core_application');
    $definition->setArgument(3, $config['base_path']);
    $definition->setArgument(4, $config['url_logout']);
    $definition->setArgument(5, $config['url_profile']);
    $definition->setArgument(6, $config['url_homepage']);
    $definition->setArgument(7, $config['app_secret']);
    $definition->setArgument(8, $config['app_name']);
    $definition->setArgument(9, $config['own_url']);
    $definition->setArgument(10, $config['own_url_api']);
    $definition->setArgument(11, $config['client_traitement']);
    $definition->setArgument(12, $config['core_api_type']);

  }

  public function getAlias()
  {
    return 'imanaging_core_application';
  }
}