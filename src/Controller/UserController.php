<?php

namespace App\Controller;

use App\Strava\Strava;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * User controller.
 */
class UserController extends AbstractController {

  /**
   * @Route("/user", name="profile", methods={"GET"})
   */
  public function profile(SessionInterface $session, Strava $strava, FormFactoryInterface $formFactory) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('/');
    }

    // Build the form.
    $params = [
      'type' => $user['activity_type'],
      'format' => $user['format'],
    ];
    echo serialize($params);
    $form = $formFactory->createBuilder(FormType::class, $params)
      ->add('type', ChoiceType::class, [
        'choices' => $strava->getActivityTypes(),
        'label' => 'Activity Type',
      ])
      ->add('format', ChoiceType::class, [
        'choices' => [
          'Imperial' => 'imperial',
          'Metric' => 'metric',
        ],
        'label' => 'Format',
      ]);
    $form = $form->getForm();

    // Render the page.
    return $this->render('user.twig', [
      'form' => $form->createView(),
    ]);
  }

  /**
   * @Route("/user", methods={"POST"})
   */
  public function profileSave(SessionInterface $session, RequestStack $requestStack, Connection $connection) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('/');
    }

    // Get the form submissions.
    $request = $requestStack->getCurrentRequest();
    $params = $request->request->get('form');
    $type = $params['type'] ?: $user['activity_type'];
    $format = $params['format'] ?: $user['format'];

    // Update the database.
    $connection->update('athletes',
      [
        'default_activity_type' => $type,
        'default_format' => $format,
      ],
      [
        'id' => $user['id'],
      ]
    );

    // Update the user session.
    $user['activity_type'] = $type;
    $user['format'] = $format;
    $session->set('user', $user);

    // Redirect to the user page.
    return $this->redirectToRoute('profile');
  }

}
