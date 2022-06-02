<?php


namespace Imanaging\CoreApplicationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder() : TreeBuilder
  {
    $treeBuilder = new TreeBuilder('imanaging_core_application');
    $rootNode = $treeBuilder->getRootNode();
    $rootNode
      ->children()
      ->variableNode('base_path')->defaultValue('base.html.twig')->info('En général base.html.twig')->end()
      ->variableNode('url_logout')->defaultValue('hephaistos_logout')->info('Nom de la route de déconnexion de l\'application')->end()
      ->variableNode('url_profile')->defaultValue('')->info('Nom de la route pour accéder à la gestion du profil')->end()
      ->variableNode('url_update_password')->defaultValue('')->info('Nom de la route pour accéder à la modification du mot de passe')->end()
      ->variableNode('url_homepage')->defaultValue('')->info('Nom de la route d\'accueil')->end()
      ->variableNode('app_secret')->defaultValue('%env(APP_SECRET)%')->end()
      ->variableNode('app_name')->defaultValue('%env(APP_NAME)%')->end()
      ->variableNode('own_url')->defaultValue('%env(OWN_URL)%')->end()
      ->variableNode('own_url_api')->defaultValue('%env(OWN_URL_API)%')->end()
      ->variableNode('client_traitement')->defaultValue('%env(CLIENT_TRAITEMENT)%')->end()
      ->variableNode('traitement_year')->defaultValue('%env(TRAITEMENT_YEAR)%')->end()
      ->variableNode('core_api_type')->defaultValue('%env(CORE_API_TYPE_APPLICATION)%')->end()
      ->end()
    ;

    return $treeBuilder;
  }
}
