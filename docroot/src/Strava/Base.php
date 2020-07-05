<?php

namespace App\Strava;

use Doctrine\DBAL\Connection;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Base class.
 */
abstract class Base {

  /**
   * Database connection.
   *
   * @var \Doctrin\DBAL\Connection
   */
  protected $connection;

  /**
   * Form Factory.
   *
   * @var \Symfony\Component\Form\FormFactoryInterface
   */
  protected $formFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * Strava.
   *
   * @var \App\Strava\Strava
   */
  protected $strava;

  /**
   * The user from the session.
   *
   * @var mixed
   */
  protected $user;

  /**
   * The message bus.
   *
   * @var \Symfony\Component\Messenger\MessageBus
   */
  protected $messageBus;

  /**
   * Constructor.
   */
  public function __construct(RequestStack $request_stack, Connection $connection, FormFactoryInterface $form_factory, Strava $strava, SessionInterface $session, MessageBusInterface $message_bus) {
    $this->requestStack = $request_stack;
    $this->connection = $connection;
    $this->formFactory = $form_factory;
    $this->strava = $strava;
    $this->session = $session;
    $this->messageBus = $message_bus;
    $this->user = $this->session->get('user');
    $this->request = $this->requestStack->getCurrentRequest();
  }

}
