<?php


namespace Imanaging\CoreApplicationBundle\Interfaces;

interface HierarchiePatrimoineTypeInterface
{
  public function getId();

  public function setId($id);

  public function getLibelle();

  public function setLibelle($libelle);

  public function getNiveau();

  public function setNiveau($niveau);

  public function getEnfant();

  public function setParent($parent);

  public function getParent();
}