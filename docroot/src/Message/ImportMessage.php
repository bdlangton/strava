<?php

namespace App\Message;

/**
 * Import message.
 */
class ImportMessage {

  /**
   * Construct an import message.
   *
   * @param string $function
   *   The function to call when invoked.
   * @param int $user_id
   *   The user ID who asked for the import.
   * @param string $type
   *   The type of import (either 'new' or a year).
   * @param int $page
   *   The page to query the Strava API.
   */
  public function __construct($function, $user_id, $type = '', $page = 1) {
    $this->function = $function;
    $this->userId = $user_id;
    $this->type = $type;
    $this->page = $page;
  }

}
