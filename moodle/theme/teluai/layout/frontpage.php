<?php
// Custom frontpage layout for AI-AA LMS home/course landing page.

defined('MOODLE_INTERNAL') || die();

global $CFG, $SITE;

if (empty($CFG->lang)) {
    $CFG->lang = 'en';
}

$bodyattributes = $OUTPUT->body_attributes(['telu-page-home']);

$navitems = [
    [
        'label' => 'Home',
        'url' => (new moodle_url('/', ['redirect' => 0]))->out(false),
        'active' => true,
    ],
    [
        'label' => 'Dashboard',
        'url' => (new moodle_url('/my/'))->out(false),
        'active' => false,
    ],
    [
        'label' => 'My Courses',
        'url' => (new moodle_url('/my/courses.php'))->out(false),
        'active' => false,
    ],
];

if (is_siteadmin()) {
    $navitems[] = [
        'label' => 'Site Administration',
        'url' => (new moodle_url('/admin/search.php'))->out(false),
        'active' => false,
    ];
}

$editswitch = '';
if (method_exists($OUTPUT, 'edit_switch')) {
    $editswitch = $OUTPUT->edit_switch();
}

$sidepreblocks = '';
if (method_exists($OUTPUT, 'blocks')) {
    $sidepreblocks = $OUTPUT->blocks('side-pre');
}

$templatecontext = [
    'sitename' => format_string(
        $SITE->shortname,
        true,
        ['context' => context_course::instance(SITEID), 'escape' => false]
    ),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'navitems' => $navitems,
    'searchurl' => (new moodle_url('/course/search.php'))->out(false),
    'chaturl' => (new moodle_url('/local/aiacademic/chat.php'))->out(false),
    'summaryurl' => (new moodle_url('/local/aiacademic/summarizer.php'))->out(false),
    'quizurl' => (new moodle_url('/local/aiacademic/quiz_generator.php'))->out(false),
    'coursesurl' => (new moodle_url('/course/index.php'))->out(false),
    'homeurl' => (new moodle_url('/', ['redirect' => 0]))->out(false),
    'telkomurl' => $OUTPUT->image_url('telkom', 'theme')->out(false),
    'usermenu' => $OUTPUT->user_menu(),
    'editswitch' => $editswitch,
    'sidepreblocks' => $sidepreblocks,
    'maincontent' => $OUTPUT->main_content(),
];

echo $OUTPUT->render_from_template('theme_teluai/frontpage', $templatecontext);
