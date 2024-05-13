<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 30/05/2017
 * Time: 12:06
 */

namespace Imanaging\CoreApplicationBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Imanaging\CoreApplicationBundle\CoreApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeclarationCoreCommand extends Command
{
  protected $em;
  private $coreApplicationService;

  public function __construct(EntityManagerInterface $em, CoreApplication $coreApplicationService)
  {
    parent::__construct();
    $this->em = $em;
    $this->coreApplicationService = $coreApplicationService;
  }

  protected function configure(): void
  {
    $this
      ->setName('core:declaration')
      ->setDescription("Déclaration du site sur le CORE.")
      ->setHelp("Déclaration du site sur le CORE")
    ;
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $response = $this->coreApplicationService->declaration();
    if ($response->getHttpCode() == 200){
      $output->writeln("<fg=green>L'application a bien été mise à jour sur le CORE.</>");
    } elseif ($response->getHttpCode() == 201){
      $output->writeln("<fg=green>L'application a bien été ajoutée pour la première fois sur le CORE.</>");
    } else {
      $output->writeln("<fg=red>Une erreur est survenue lors de la déclaration sur le CORE (HTTP CODE : ".$response->getHttpCode()." ).</>");
    }
    return Command::SUCCESS;
  }
}