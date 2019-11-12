<?php

namespace App\Strava;

use Doctrine\DBAL\Connection;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Base class.
 */
abstract class Base {

  /**
   * Database connection.
   *
   * @var \Doctrin\DBAL\Connection
   */
  private $connection;

  /**
   * Form Factory.
   *
   * @var \Symfony\Component\Form\FormFactoryInterface
   */
  private $formFactory;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * Session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  private $session;

  /**
   * Strava.
   *
   * @var \App\Strava\Strava
   */
  private $strava;

  /**
   * Constructor.
   */
  public function __construct(RequestStack $request_stack, Connection $connection, FormFactoryInterface $form_factory, Strava $strava, SessionInterface $session) {
    $this->requestStack = $request_stack;
    $this->connection = $connection;
    $this->formFactory = $form_factory;
    $this->strava = $strava;
    $this->session = $session;
  }

}
