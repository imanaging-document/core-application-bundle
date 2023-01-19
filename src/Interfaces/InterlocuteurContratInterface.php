<?php


namespace Imanaging\CoreApplicationBundle\Interfaces;

interface InterlocuteurContratInterface
{
  public function setContrat(ContratInterface $contrat);

  public function getContrat() : ContratInterface;

  public function getInterlocuteur() : InterlocuteurInterface;

  public function setInterlocuteur(InterlocuteurInterface $interlocuteur);
}