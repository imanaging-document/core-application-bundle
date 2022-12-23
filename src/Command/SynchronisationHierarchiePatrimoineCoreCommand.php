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

  protected function configure(){
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
  protected function execute(InputInterface $input, OutputInterface $output){
    $res = $this->coreApplicationService->synchroniserHierarchiePatrimoine($output);
    $output->writeln('');
    if ($res['success']){
      $output->writeln("<fg=green>La synchronisation de la hierarchie patrimoine depuis le CORE a réussi..</>");
    } else {
      $output->writeln("<fg=red>".$res['error_message']."</>");
    }
    return Command::SUCCESS;
  }
}
