<?php

namespace App\Strava;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stats class.
 */
class Stats extends Base {

  /**
   * Build the form.
   */
  private function buildForm() {
    $this->params = $this->request->query->get('form') ?? [];
    $this->generating = !empty($this->params['stat_type']);
    $this->params += [
      'type' => $this->user['activity_type'] ?: 'Run',
      'stat_type' => 'distance',
      'duration' => 7,
      'excluding_races' => [],
    ];
    $form = $this->formFactory->createBuilder(FormType::class, $this->params)
      ->add('type', ChoiceType::class, [
        'choices' => $this->strava->getActivityTypes(FALSE),
        'label' => FALSE,
      ])
      ->add('stat_type', ChoiceType::class, [
        'choices' => [
          'Distance' => 'distance',
          'Elevation Gain' => 'total_elevation_gain',
          'Time' => 'elapsed_time',
        ],
        'label' => FALSE,
      ])
      ->add('duration', TextType::class, [
        'label' => 'Days',
      ])
      ->add('excluding_races', ChoiceType::class, [
        'choices' => ['Exclude Races' => 'excluding_races'],
        'expanded' => TRUE,
        'multiple' => TRUE,
        'label' => FALSE,
      ]);
    $this->form = $form->getForm();
  }

  private function generateStat() {
    // Build the query.
    $sql = 'SELECT DATEDIFF(start_date_local, "2000-01-01") day, SUM(' . $this->params['stat_type'] . ') stat ';
    $sql .= 'FROM activities ';
    $sql .= 'WHERE athlete_id = ? AND type = ? ';
    if (!empty($this->params['excluding_races'])) {
      $sql .= 'AND workout_type <> 1 ';
    }
    $sql .= 'GROUP BY start_date_local ';
    $sql .= 'ORDER BY start_date_local';
    $results = $this->connection->executeQuery($sql, [
      $this->user['id'],
      $this->params['type'],
    ])->fetchAll();
    $days = [];
    foreach ($results as $result) {
      $days[$result['day']] = $result['stat'];
    }
    $days += array_fill_keys(range(min(array_keys($days)), max(array_keys($days))), 0);
    ksort($days);

    // Find the biggest data.
    $biggest_date = NULL;
    $biggest_stat = 0;
    $i = 0;
    foreach ($days as $key => $day) {
      $slice = array_slice($days, $i, $this->params['duration']);
      $current_stat = array_sum($slice);
      if ($current_stat > $biggest_stat) {
        $biggest_stat = $current_stat;
        $biggest_date = $key;
      }
      $i++;
    }
    $start_timestamp = strtotime('+' . $biggest_date . ' days', strtotime('2000-01-01'));
    $start_date = new \DateTime();
    $start_date->setTimestamp($start_timestamp);
    $end_timestamp = strtotime('+' . ($this->params['duration'] - 1) . ' days', $start_timestamp);
    $end_date = new \DateTime();
    $end_date->setTimestamp($end_timestamp);

    // Update or insert the stat.
    $sql = 'SELECT * ';
    $sql .= 'FROM stats ';
    $sql .= 'WHERE athlete_id = ? AND activity_type = ? AND duration = ? AND stat_type = ? AND excluding_races = ?';
    $result = $this->connection->executeQuery($sql, [
      $this->user['id'],
      $this->params['type'],
      $this->params['duration'],
      $this->params['stat_type'],
      !empty($this->params['excluding_races']),
    ])->fetchAll();
    if (empty($result)) {
      $this->connection->insert('stats', [
        'athlete_id' => $this->user['id'],
        'activity_type' => $this->params['type'],
        'duration' => $this->params['duration'],
        'stat_type' => $this->params['stat_type'],
        'stat' => $biggest_stat,
        'start_date' => $start_date->format('Y-m-d'),
        'end_date' => $end_date->format('Y-m-d'),
        'excluding_races' => !empty($this->params['excluding_races']) ? 1 : 0,
      ]);
      $result = $this->connection->executeQuery($sql, [
        $this->user['id'],
        $this->params['type'],
        $this->params['duration'],
        $this->params['stat_type'],
        !empty($this->params['excluding_races']),
      ])->fetchAll();
    }
    else {
      $result = $this->connection->update('stats', [
        'stat' => $biggest_stat,
        'start_date' => $start_date->format('Y-m-d'),
        'end_date' => $end_date->format('Y-m-d'),
      ],
      [
        'athlete_id' => $this->user['id'],
        'activity_type' => $this->params['type'],
        'duration' => $this->params['duration'],
        'stat_type' => $this->params['stat_type'],
        'excluding_races' => !empty($this->params['excluding_races']) ? 1 : 0,
      ]);
    }
  }

  private function query() {
    // Query all stats from this user.
    $sql = 'SELECT * ';
    $sql .= 'FROM stats ';
    $sql .= 'WHERE athlete_id = ? ';
    $sql .= 'ORDER BY activity_type, stat_type, duration';
    $this->stats = $this->connection->executeQuery($sql, [$this->user['id']], [\PDO::PARAM_INT])->fetchAll();
  }

  /**
   * Update the stat.
   *
   * @param int $id
   *   The ID of the stat.
   */
  public function updateStat($id) {
    // Find the stat.
    $sql = 'SELECT * ';
    $sql .= 'FROM stats ';
    $sql .= 'WHERE athlete_id = ? AND id = ?';
    $stat = $this->connection->executeQuery($sql, [
      $this->user['id'],
      $id,
    ],
    [
      \PDO::PARAM_INT,
      \PDO::PARAM_INT,
    ])->fetch();

    // Update the stat.
    if (!empty($stat)) {
      $subRequest = Request::create(
        '/big',
        'GET',
        [
          'type' => $stat['activity_type'],
          'stat_type' => $stat['stat_type'],
          'duration' => $stat['duration'],
          'excluding_races' => $stat['excluding_races'] ? ['excluding_races'] : [],
        ],
        $this->request->cookies->all(),
        [],
        $this->request->server->all()
      );
      if ($this->request->getSession()) {
        $subRequest->setSession($this->request->getSession());
      }
      // $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, FALSE);
      $this->session->getFlashBag()->add('strava', 'Your strava stat was updated!');
    }
    else {
      $this->session->getFlashBag()->add('strava', 'We could not find a stat to update.');
    }
  }

  /**
   * Delete the stat.
   *
   * @param int $id
   *   The stat ID to delete.
   */
  public function deleteStat($id) {
    // Only let the user delete the stat if they own it.
    $result = $this->connection->delete('stats', [
      'id' => $id,
      'athlete_id' => $this->user['id'],
    ]);

    if ($result) {
      $this->session->getFlashBag()->add('strava', 'Your strava stat was deleted!');
    }
    else {
      $this->session->getFlashBag()->add('strava', 'We could not find a stat to delete.');
    }
  }

  /**
   * Render the activities.
   *
   * @return array
   *   Return an array of render data.
   */
  public function render() {
    $this->buildForm();
    if ($this->generating) {
      $this->generateStat();
    }
    $this->query();

    foreach ($this->stats as &$stat) {
      if ($stat['stat_type'] == 'distance') {
        $stat['stat_type'] = 'Distance';
        $stat['stat'] = $this->strava->convertDistance($stat['stat'], $this->user['format']) . ' ' . ($this->user['format'] == 'imperial' ? 'miles' : 'kilometers');
      }
      elseif ($stat['stat_type'] == 'total_elevation_gain') {
        $stat['stat_type'] = 'Elevation Gain';
        $stat['stat'] = $this->strava->convertElevationGain($stat['stat'], $this->user['format']) . ' ' . ($this->user['format'] == 'imperial' ? 'feet' : 'meters');
      }
      elseif ($stat['stat_type'] == 'elapsed_time') {
        $stat['stat_type'] = 'Time';
        $minutes = $this->strava->convertTimeFormat($stat['stat'], 'i');
        $hours = $this->strava->convertTimeFormat($stat['stat'], 'H');
        $days = $this->strava->convertTimeFormat($stat['stat'], 'j') - 1;
        if ($days > 0) {
          $hours += $days * 24;
        }
        $stat['stat'] = $hours . ' hours, ' . $minutes . ' minutes';
      }
      $stat['excluding_races'] = empty($stat['excluding_races']) ? '' : 'Yes';
      $stat['start_date'] = $this->strava->convertDateFormat($stat['start_date']);
      $stat['end_date'] = $this->strava->convertDateFormat($stat['end_date']);
    }

    // Render the page.
    return [
      'form' => $this->form->createView(),
      'stats' => $this->stats,
    ];
  }

}
