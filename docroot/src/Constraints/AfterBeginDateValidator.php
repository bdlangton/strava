<?php

namespace App\Constraints;

use Symfony\Component\Validator\Constraints\GreaterThanOrEqualValidator;

/**
 * Validates values are greater than or equal to the previous (>=).
 */
class AfterBeginDateValidator extends GreaterThanOrEqualValidator
{

}
