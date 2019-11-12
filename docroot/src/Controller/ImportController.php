<?php

namespace App\Controller;

use App\Strava\Import;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Import functionality controller.
 */
class ImportController extends AbstractController {

  /**
   * @Route("/import", name="import", methods={"GET"})
   */
  public function import(SessionInterface $session, Import $import) {
    // Check the session.
    $user = $session->get('user');
    if (empty($user)) {
      return $this->redirectToRoute('home');
    }

    return $this->render('import.twig', $import->render());
  }

}
