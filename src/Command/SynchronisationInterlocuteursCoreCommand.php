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
    $action = $this->coreApplicationService->getActionEnAttenteOuEnCours(CoreSynchronisationActionInterface::TYPE_SYNCHRONISATION_INTERLOCUTEURS);

    if (!$action) {
      $res = $this->coreApplicationService->createAction(CoreSynchronisationActionInterface::TYPE_SYNCHRONISATION_INTERLOCUTEURS);
      if ($res['success']) {
        $action = $res['synchronisation_action'];
      } else {
        $output->writeln("<fg=red>Impossible de créer une nouvelle action de synchronisation</>");
        return Command::FAILURE;
      }
    } elseif ($action->getStatut() == CoreSynchronisationActionInterface::STATUT_EN_COURS) {
      $output->writeln("<fg=red>Action déjà en cours de traitement depuis moins de 2 heures.</>");
      return Command::SUCCESS;
    }

    $res = $this->coreApplicationService->synchronisationInterlocuteurs($action, $output);
    $output->writeln('');
    if ($res['success']){
      $output->writeln("<fg=green>La synchronisation des interlocuteurs depuis le CORE a réussi.</>");
    } else {
      $output->writeln("<fg=red>".$res['error_message']."</>");
    }
    return Command::SUCCESS;

  }
}
