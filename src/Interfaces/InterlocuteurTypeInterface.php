<?php


namespace Imanaging\CoreApplicationBundle\Interfaces;

interface InterlocuteurTypeInterface
{
  public function getId();

  public function setId($id);

  public function getLibelle() : string;

  public function setLibelle($libelle);

  public function getGroupe() : string;

  public function setGroupe($libelle);

  public function getIdCore() : string;

  public function setIdCore($idCore);

  public function isVisibleRecherche() : bool;

  public function setVisibleRecherche(bool $visibleRecherche);
}