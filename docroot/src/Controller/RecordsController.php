<?php

namespace App\Controller;

use App\Strava\Records;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * KOM and PR records controller.
 */
class RecordsController extends AbstractController {

  /**
   * @Route("/records", name="records")
   */
  public function records(SessionInterface $session, Records $records) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    // Render the page.
    return $this->render('records.twig', $records->render());
  }

}
