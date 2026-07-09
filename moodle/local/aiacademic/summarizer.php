<?php
// ============================================================================
// Moodle local_aiacademic — Material Summarizer Page
// Entry page to view and generate summaries of course materials
// ============================================================================

require_once(__DIR__ . '/../../config.php');


$courseid = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

// Set up page parameters
$PAGE->set_url(new moodle_url('/local/aiacademic/summarizer.php', array('courseid' => $courseid, 'cmid' => $cmid)));

// Require course-aware login.
require_login($courseid);

// Verify course context
$coursecontext = context_course::instance($courseid);
require_capability('local/aiacademic:summarize', $coursecontext);

$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('ai_material_summarizer', 'local_aiacademic'));
$PAGE->set_heading(get_string('ai_material_summarizer', 'local_aiacademic'));

// Add style sheet
$PAGE->requires->css(new moodle_url('/local/aiacademic/styles.css'));

// Output page header
echo $OUTPUT->header();

// Fetch files available for summarization in the course
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

// Setup template data
$templatedata = array(
    'sesskey' => sesskey(),
    'courseid' => $courseid,
    'cmid' => $cmid,
    'materials' => $materials,
    'has_materials' => !empty($materials),
    'labels' => array(
        'generate' => get_string('summary_generate', 'local_aiacademic'),
        'regenerate' => get_string('summary_regenerate', 'local_aiacademic'),
        'executive' => get_string('summary_executive', 'local_aiacademic'),
        'key_points' => get_string('summary_key_points', 'local_aiacademic'),
        'concepts' => get_string('summary_concepts', 'local_aiacademic'),
        'glossary' => get_string('summary_glossary', 'local_aiacademic'),
        'study_guide' => get_string('summary_study_guide', 'local_aiacademic'),
        'loading' => get_string('summary_loading', 'local_aiacademic')
    )
);

// Render template
echo $OUTPUT->render_from_template('local_aiacademic/summarizer', $templatedata);

// Initialize AMD module
$PAGE->requires->js_call_amd('local_aiacademic/summarizer', 'init', array(array(
    'sesskey' => sesskey(),
    'courseid' => $courseid,
    'cmid' => $cmid
)));

// Output page footer
echo $OUTPUT->footer();
