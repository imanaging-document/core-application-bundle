<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 15/12/2019
 * Time: 11:28
 */

namespace Imanaging\CoreApplicationBundle\Command;

use Imanaging\CoreApplicationBundle\CoreApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SynchronisationUtilisateursCoreCommand extends Command
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
      ->setName('core:synchronisation:utilisateurs')
      ->setDescription("Synchronisation des utilisateurs depuis le CORE")
    ;
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int|null|void
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $res = $this->coreApplicationService->synchroniserUsers();
    if ($res['success']){
      $output->writeln("<fg=green>La synchronisation des utilisateurs depuis le CORE a réussi..</>");
    } else {
      $output->writeln("<fg=red>".$res['error_message']."</>");
    }
    return Command::SUCCESS;
  }
}
