<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Routing\Annotation\Route;

/**
 * User controller.
 */
class UserController extends AbstractController {

  /**
   * @Route("/user", methods={"GET"})
   */
  public function profile() {
    // Check the session.
    $user = $app['session']->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('/');
    }

    // Build the form.
    $params = $request->query->all();
    $params += [
      'type' => $user['activity_type'],
      'format' => $user['format'],
    ];
    $form = $app['form.factory']->createNamedBuilder(NULL, FormType::class, $params)
      ->add('type', ChoiceType::class, [
        'choices' => $app['strava']->getActivityTypes(),
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
  public function profileSave() {
    // Check the session.
    $user = $app['session']->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('/');
    }

    // Get the form submissions.
    $type = $request->get('type') ?: $user['activity_type'];
    $format = $request->get('format') ?: $user['format'];

    // Update the database.
    $app['db']->update('athletes',
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
    $app['session']->set('user', $user);

    // Redirect to the user page.
    return $app->redirect('/user');
  }

}
