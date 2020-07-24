<?php


namespace Imanaging\CoreApplicationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder('imanaging_core_application');
    $rootNode = $treeBuilder->getRootNode();
    $rootNode
      ->children()
      ->variableNode('base_path')->defaultValue('base.html.twig')->info('En général base.html.twig')->end()
      ->end()
    ;

    return $treeBuilder;
  }
}
