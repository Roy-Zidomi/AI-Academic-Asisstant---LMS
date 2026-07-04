<?php
// Public landing page layout for theme_teluai.

defined('MOODLE_INTERNAL') || die();

global $CFG, $SITE;

if (empty($CFG->lang)) {
    $CFG->lang = 'en';
}

$sitecontext = context_course::instance(SITEID);
$sitename = format_string($SITE->shortname, true, ['context' => $sitecontext, 'escape' => false]);

$templatecontext = [
    'sitename' => $sitename,
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(['telu-page-home', 'telu-landing-page']),
    'maincontent' => $OUTPUT->main_content(),
    'logourl' => (new moodle_url('/theme/teluai/pix/logo-telkom-university.png'))->out(false),
    'homeurl' => (new moodle_url('/'))->out(false),
    'loginurl' => (new moodle_url('/login/index.php'))->out(false),
    'year' => date('Y'),
];

echo $OUTPUT->render_from_template('theme_teluai/landing', $templatecontext);
