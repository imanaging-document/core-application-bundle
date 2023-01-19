<?php


namespace Imanaging\CoreApplicationBundle\Interfaces;

use Imanaging\ZeusUserBundle\Interfaces\UserInterface;

interface InterlocuteurInterface
{
  public function getId();

  public function setId($id);

  public function getLibelle() : string;

  public function setLibelle($libelle);

  public function getIdCore() : string;

  public function setIdCore($idCore);

  public function getType() : InterlocuteurTypeInterface;

  public function setType($type);

  public function getUser() : ?UserInterface;

  public function setUser($user);

  public function getContratsInterlocuteurs();
}