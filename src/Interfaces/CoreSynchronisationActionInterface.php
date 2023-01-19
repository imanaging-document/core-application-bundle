<?php

namespace Imanaging\CoreApplicationBundle\Interfaces;

use DateTime;

interface CoreSynchronisationActionInterface
{
  const STATUT_EN_ATTENTE='en_attente';
  const STATUT_EN_COURS='en_cours';
  const STATUT_EN_ERREUR='en_erreur';
  const STATUT_TERMINE='termine';
  const TYPE_SYNCHRONISATION_INTERLOCUTEURS = 'synchronisation_interlocuteurs';
  const TYPE_SYNCHRONISATION_HIERARCHIE_PATRIMOINE = 'synchronisation_hierarchie_patrimoine';

  public function getId();
  public function getTypeSynchronisation();
  public function setTypeSynchronisation(string $typeSynchronisation);
  public function getDateCreation();
  public function setDateCreation(DateTime $dateCreation);
  public function getDateLancement();
  public function setDateLancement(DateTime $dateLancement);
  public function getDateFin();
  public function setDateFin(DateTime $dateFin);
  public function getDuree();
  public function setDuree(float $duree);
  public function getStatut();
  public function setStatut(string $statut);
  public function getErrorMessage();
  public function setErrorMessage(string $errorMessage);
}