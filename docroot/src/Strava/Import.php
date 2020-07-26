<?php

namespace App\Strava;

use App\Message\ImportMessage;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;

/**
 * Import.
 */
class Import extends Base {

  /**
   * Build the form.
   */
  public function buildForm() {
    $this->params = $this->request->query->get('form') ?? [];
    $this->import_type = !empty($this->params['type']) ? $this->params['type'] : NULL;
    $this->params += [
      'type' => 'new',
      'starred_segments' => FALSE,
    ];
    $this->params['starred_segments'] = !empty($this->params['starred_segments']);
    $form = $this->formFactory->createBuilder(FormType::class, $this->params)
      ->add('type', ChoiceType::class, [
        'choices' => [
          'None' => '',
          'New Activities' => 'new',
          '2020 Activities' => '2020',
          '2019 Activities' => '2019',
          '2018 Activities' => '2018',
          '2017 Activities' => '2017',
          '2016 Activities' => '2016',
          '2015 Activities' => '2015',
          '2014 Activities' => '2014',
          '2013 Activities' => '2013',
          '2012 Activities' => '2012',
          '2011 Activities' => '2011',
          '2010 Activities' => '2010',
        ],
        'label' => 'Import Activities',
        'required' => FALSE,
      ])
      ->add('starred_segments', CheckboxType::class, [
        'label' => 'Import Starred Segments',
        'required' => FALSE,
        'value' => TRUE,
      ]);
    $this->form = $form->getForm();
  }

  /**
   * Render.
   */
  public function render() {
    $this->buildForm();

    // Add import of activities to the message queue.
    if (!empty($this->import_type)) {
      $this->messageBus->dispatch(new ImportMessage('import', $this->user['id'], $this->import_type));
      $this->session->getFlashBag()->add('strava', 'Queued your activities for import. Updates may not show up right away.');
    }

    // Add import of starred segments to the message queue.
    if (!empty($this->params['starred_segments'])) {
      $this->messageBus->dispatch(new ImportMessage('importStarredSegments', $this->user['id']));
      $this->session->getFlashBag()->add('strava', 'Queued your starred segments for import. Updates may not show up right away.');
    }

    return [
      'form' => $this->form->createView(),
    ];
  }

}
