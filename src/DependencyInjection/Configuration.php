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
      ->variableNode('url_logout')->defaultValue('hephaistos_logout')->info('Nom de la route de déconnexion de l\'application')->end()
      ->variableNode('url_profile')->defaultValue('')->info('Nom de la route pour accéder à la gestion du profil')->end()
      ->variableNode('url_homepage')->defaultValue('')->info('Nom de la route d\'accueil')->end()
      ->end()
    ;

    return $treeBuilder;
  }
}
