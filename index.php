<?php
require(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/dashboard/index.php'));
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title(get_string('pluginname', 'local_dashboard'));
// Remover o heading para não exibir "Portal Acadêmico Inteligente"
// $PAGE->set_heading(get_string('pluginname', 'local_dashboard'));

$PAGE->requires->css('/local/dashboard/styles.css');

$renderer = $PAGE->get_renderer('local_dashboard');
$data = \local_dashboard\local\service::get_dashboard_data($USER);

$data['userfullname'] = fullname($USER);
$data['coursesempty'] = empty($data['courses']);
$data['eventsempty']  = empty($data['events']);
$data['mycoursesurl'] = (new moodle_url('/my/courses.php'))->out(false);

echo $OUTPUT->header();

// Incluir JavaScript para auto-refresh do contador de mensagens
$PAGE->requires->js('/local/dashboard/js/message_counter.js');

echo $renderer->render_landing($data);
echo $OUTPUT->footer();
