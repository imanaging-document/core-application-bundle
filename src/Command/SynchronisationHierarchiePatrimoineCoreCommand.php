<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 15/12/2019
 * Time: 11:28
 */

namespace Imanaging\CoreApplicationBundle\Command;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\CoreApplicationBundle\Interfaces\CoreSynchronisationActionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SynchronisationHierarchiePatrimoineCoreCommand extends Command
{
  private $coreApplicationService;

  public function __construct(CoreApplication $coreApplicationService)
  {
    parent::__construct();
    $this->coreApplicationService = $coreApplicationService;
  }

  protected function configure(): void
  {
    $this
      ->setName('core:synchronisation:hierarchie-patrimoine')
      ->setDescription("Synchronisation de la hierarchie patrimoine depuis le CORE")
      ->addArgument('id_ouranos', InputArgument::OPTIONAL)
    ;
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int|null|void
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $action = $this->coreApplicationService->getActionEnAttente(CoreSynchronisationActionInterface::TYPE_SYNCHRONISATION_HIERARCHIE_PATRIMOINE);
    if ($action instanceof CoreSynchronisationActionInterface) {
      $res = $this->coreApplicationService->synchroniserHierarchiePatrimoine($action, $output);
      $output->writeln('');
      if ($res['success']){
        $output->writeln("<fg=green>La synchronisation de la hierarchie patrimoine depuis le CORE a réussi..</>");
      } else {
        $output->writeln("<fg=red>".$res['error_message']."</>");
      }
      return Command::SUCCESS;
    } else {
      $output->writeln("<fg=red>Aucune action en attente</>");
      return Command::FAILURE;
    }
  }
}
