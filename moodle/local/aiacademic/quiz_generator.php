<?php
// ============================================================================
// Moodle local_aiacademic — Quiz Generator Page
// Entrance page for lecturers to generate and review draft quizzes
// ============================================================================

require_once(__DIR__ . '/../../config.php');


$courseid = required_param('id', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

// Set up page parameters
$PAGE->set_url(new moodle_url('/local/aiacademic/quiz_generator.php', array('id' => $courseid, 'cmid' => $cmid)));

// Require course-aware login.
require_login($courseid);

// Verify course context & capability
$coursecontext = context_course::instance($courseid);
require_capability('local/aiacademic:generatequiz', $coursecontext);

$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('quiz_generator_title', 'local_aiacademic'));
$PAGE->set_heading(get_string('quiz_generator_title', 'local_aiacademic'));

// Add plugin custom stylesheet
$PAGE->requires->css(new moodle_url('/local/aiacademic/styles.css'));

// Output page header
echo $OUTPUT->header();

// Fetch available resources in the course for parsing
global $DB;
$modinfo = get_fast_modinfo($courseid);
$materials = array();

if (isset($modinfo->instances['resource'])) {
    foreach ($modinfo->instances['resource'] as $cmrec) {
        if ($cmrec->uservisible) {
            $materials[] = array(
                'cmid' => (int)$cmrec->id,
                'name' => $cmrec->name,
                'selected' => ($cmrec->id == $cmid) ? true : false
            );
        }
    }
}

// Fetch existing generated quizzes list for this course
$drafts = $DB->get_records_select(
    'local_aiacademic_genquizzes',
    'courseid = :courseid',
    array('courseid' => $courseid),
    'timecreated DESC',
    'id, source_filename, difficulty, requested_count, status, timecreated'
);

$draftlist = array();
foreach ($drafts as $d) {
    $statuslabel = 'Pending';
    $status = (int)$d->status;
    if ($status === 1) {
        $statuslabel = 'Awaiting Review';
    } else if ($status === 2) {
        $statuslabel = 'Reviewed';
    } else if ($status === 3) {
        $statuslabel = 'Published';
    } else if ($status === 4) {
        $statuslabel = 'Failed';
    }

    $draftlist[] = array(
        'id' => (int)$d->id,
        'filename' => $d->source_filename,
        'difficulty' => ucfirst($d->difficulty),
        'count' => (int)$d->requested_count,
        'status' => $statuslabel,
        'timecreated' => userdate($d->timecreated)
    );
}

// Setup template data
$templatedata = array(
    'sesskey' => sesskey(),
    'courseid' => $courseid,
    'materials' => $materials,
    'has_materials' => !empty($materials),
    'drafts' => $draftlist,
    'has_drafts' => !empty($draftlist),
    'quiz_generator_title' => get_string('quiz_generator_title', 'local_aiacademic'),
    'quiz_difficulty' => get_string('quiz_difficulty', 'local_aiacademic'),
    'difficulty_easy' => get_string('quiz_difficulty_easy', 'local_aiacademic'),
    'difficulty_medium' => get_string('quiz_difficulty_medium', 'local_aiacademic'),
    'difficulty_hard' => get_string('quiz_difficulty_hard', 'local_aiacademic'),
    'difficulty_mixed' => get_string('quiz_difficulty_mixed', 'local_aiacademic'),
    'num_questions_label' => get_string('quiz_num_questions', 'local_aiacademic'),
    'types_label' => get_string('quiz_types', 'local_aiacademic'),
    'type_mcq' => get_string('quiz_type_mcq', 'local_aiacademic'),
    'type_tf' => get_string('quiz_type_tf', 'local_aiacademic'),
    'type_essay' => get_string('quiz_type_essay', 'local_aiacademic'),
    'generate_label' => get_string('quiz_generate_btn', 'local_aiacademic'),
    'generating_label' => get_string('quiz_generating', 'local_aiacademic'),
    'quiz_publish_btn' => get_string('quiz_publish_btn', 'local_aiacademic'),
    'quiz_publish_settings' => get_string('quiz_publish_settings', 'local_aiacademic'),
    'quiz_name_label' => get_string('quiz_name_label', 'local_aiacademic'),
    'quiz_open_time_label' => get_string('quiz_open_time_label', 'local_aiacademic'),
    'quiz_close_time_label' => get_string('quiz_close_time_label', 'local_aiacademic'),
    'quiz_time_limit_label' => get_string('quiz_time_limit_label', 'local_aiacademic'),
    'quiz_attempts_label' => get_string('quiz_attempts_label', 'local_aiacademic'),
    'quiz_grade_label' => get_string('quiz_grade_label', 'local_aiacademic'),
    'quiz_questions_per_page_label' => get_string('quiz_questions_per_page_label', 'local_aiacademic'),
    'quiz_shuffle_label' => get_string('quiz_shuffle_label', 'local_aiacademic'),
    'quiz_visible_label' => get_string('quiz_visible_label', 'local_aiacademic'),
    'quiz_name_placeholder' => get_string('quiz_name_placeholder', 'local_aiacademic')
);

// Render template
echo $OUTPUT->render_from_template('local_aiacademic/quiz_generator', $templatedata);

$PAGE->requires->js_call_amd('local_aiacademic/quiz_generator', 'init', array(array(
    'sesskey' => sesskey(),
    'courseid' => $courseid,
    'cmid' => $cmid
)));

// Output page footer
echo $OUTPUT->footer();
