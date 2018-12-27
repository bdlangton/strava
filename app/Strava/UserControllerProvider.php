<?php

namespace Strava;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;

/**
 * User controller.
 */
class UserControllerProvider implements ControllerProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function connect(Application $app) {
    $user = $app['controllers_factory'];

    // User profile settings.
    $user->get('/user', function (Request $request) use ($app) {
      // Check the session.
      $user = $app['session']->get('user');
      if (empty($user)) {
        return $app->redirect('/');
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
      return $app['twig']->render('user.twig', [
        'form' => $form->createView(),
      ]);
    });

    // User profile settings post.
    $user->post('/user', function (Request $request) use ($app) {
      // Check the session.
      $user = $app['session']->get('user');
      if (empty($user)) {
        return $app->redirect('/');
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
    });

    return $user;
  }

}
