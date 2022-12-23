<?php


namespace Imanaging\CoreApplicationBundle\Interfaces;

interface HierarchiePatrimoineInterface
{
  public function getId();

  public function setId($id);

  public function getCode();

  public function setCode($code);

  public function getLibelle();

  public function setLibelle($libelle);

  public function getNbPatrimoines();

  public function setNbPatrimoines($nbPatrimoines);

  public function getType();

  public function setType(HierarchiePatrimoineTypeInterface $hierarchiePatrimoineType);

  public function getEnfants();

  public function setParent($parent);

  public function getParent();
}