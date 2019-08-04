<?php

namespace App\Command;

use App\Strava\Strava;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshTokensCommand extends Command {

  /**
   * The name to use when calling bin/console.
   *
   * @var string
   */
  protected static $defaultName = 'strava:refresh-tokens';

  /**
   * The Strava Service.
   *
   * @var \App\Strava\Strava
   */
  private $strava;

  /**
   * Constructor.
   *
   * @param \App\Strava\Strava $strava
   *   The Strava service.
   */
  public function __construct(Strava $strava) {
    $this->strava = $strava;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setDescription('Refresh user tokens.')
      ->setHelp('Refresh user tokens that are about to expire.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->strava->refreshTokens();
  }

}
