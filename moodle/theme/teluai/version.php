<?php
// ============================================================================
// Tel-U AI LMS Theme — Plugin Version
// ============================================================================

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026070308;
$plugin->requires  = 2022111800;    // Moodle 4.1+
$plugin->component = 'theme_teluai';
$plugin->dependencies = array(
    'theme_boost' => 2022112800,
);
