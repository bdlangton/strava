<?php

namespace App\Constraints;

use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class AfterBeginDate extends GreaterThanOrEqual {

  /**
   * The message to display when there is an error.
   *
   * @var string
   */
  public $message = 'This value should be after the begin date.';

}
