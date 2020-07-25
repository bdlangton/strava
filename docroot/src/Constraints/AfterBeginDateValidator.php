<?php

namespace App\Constraints;

use Symfony\Component\Validator\Constraints\GreaterThanOrEqualValidator;

/**
 * Validates values are greater than or equal to the previous (>=).
 */
class AfterBeginDateValidator extends GreaterThanOrEqualValidator {

  /**
   * {@inheritdoc}
   */
  protected function compareValues($value1, $value2) {
    if (empty($value1) || empty($value2)) {
      return TRUE;
    }

    $value1 = new \DateTime($value1);
    $value2 = new \DateTime($value2);

    return $value1->getTimestamp() >= $value2->getTimestamp();
  }

}
