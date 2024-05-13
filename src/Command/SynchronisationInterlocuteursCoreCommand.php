<?php

namespace Imanaging\CoreApplicationBundle\Command;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Imanaging\CoreApplicationBundle\Interfaces\CoreSynchronisationActionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SynchronisationInterlocuteursCoreCommand extends Command
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
      ->setName('core:synchronisation:interlocuteurs')
      ->setDescription("Synchronisation des interlocuteurs depuis le CORE")
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
    $action = $this->coreApplicationService->getActionEnAttente(CoreSynchronisationActionInterface::TYPE_SYNCHRONISATION_INTERLOCUTEURS);
    if ($action instanceof CoreSynchronisationActionInterface) {
      $res = $this->coreApplicationService->synchronisationInterlocuteurs($action, $output);
      $output->writeln('');
      if ($res['success']){
        $output->writeln("<fg=green>La synchronisation des interlocuteurs depuis le CORE a réussi.</>");
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
