<?php
// ============================================================================
// Tel-U AI LMS Theme — Custom Login Layout
// Split-screen modern login with AI-AA LMS branding.
// ============================================================================

defined('MOODLE_INTERNAL') || die();

global $CFG;

if (empty($CFG->lang)) {
    $CFG->lang = 'en';
}

$bodyattributes = $OUTPUT->body_attributes(['telu-page-login']);

$templatecontext = [
    'sitename' => format_string(
        $SITE->shortname,
        true,
        ['context' => context_course::instance(SITEID), 'escape' => false]
    ),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
];

echo $OUTPUT->render_from_template('theme_teluai/login', $templatecontext);
