<?php

namespace Imanaging\CoreApplicationBundle\Service;

final class UserApiException extends \RuntimeException
{
  public function __construct(string $message, public readonly int $httpStatus)
  {
    parent::__construct($message);
  }
}
