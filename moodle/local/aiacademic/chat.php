<?php
// ============================================================================
// Moodle local_aiacademic — Chat Assistant Page
// Entrance page for students to interact with the AI Academic Assistant
// ============================================================================

require_once(__DIR__ . '/../../config.php');

// Ensure user is logged in
require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);

// Capability check & context setup
if ($courseid > 0) {
    $context = context_course::instance($courseid);
    require_login($courseid);
} else {
    $context = context_system::instance();
}
if (!has_capability('local/aiacademic:usechat', $context)) {
    require_capability('local/aiacademic:usechat', context_system::instance());
}

// Set up page parameters
$PAGE->set_url(new moodle_url('/local/aiacademic/chat.php', array('courseid' => $courseid)));
$PAGE->set_context($context);
$PAGE->set_title(get_string('chat_title', 'local_aiacademic'));
$PAGE->set_heading(get_string('chat_title', 'local_aiacademic'));

// Add plugin styles
$PAGE->requires->css(new moodle_url('/local/aiacademic/styles.css'));

// Output page header
echo $OUTPUT->header();

// Setup template data
$templatedata = array(
    'sesskey' => sesskey(),
    'courseid' => $courseid,
    'welcome_message' => get_string('chat_welcome', 'local_aiacademic'),
    'placeholder' => get_string('chat_placeholder', 'local_aiacademic'),
    'send_label' => get_string('chat_send', 'local_aiacademic'),
    'disclaimer' => get_string('chat_disclaimer', 'local_aiacademic'),
    'new_session_label' => get_string('chat_new_session', 'local_aiacademic'),
    'chat_history_label' => get_string('chat_history', 'local_aiacademic')
);

// Render Mustache template
echo $OUTPUT->render_from_template('local_aiacademic/chat', $templatedata);

$PAGE->requires->js_call_amd('local_aiacademic/chat', 'init', array(array(
    'sesskey' => sesskey(),
    'courseid' => $courseid
)));

// Output page footer
echo $OUTPUT->footer();
