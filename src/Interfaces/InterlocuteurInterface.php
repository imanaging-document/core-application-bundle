<?php

namespace Imanaging\CoreApplicationBundle\Interfaces;

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

  public function getUsers();

  public function setUsers($users);

  public function getContratsInterlocuteurs();
}