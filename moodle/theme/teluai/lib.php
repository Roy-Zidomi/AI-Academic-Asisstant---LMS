<?php
// Tel-U AI LMS Theme library functions.

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content for the theme.
 *
 * This child theme keeps Boost as the base. Reading Boost's default preset
 * directly avoids child-theme import path issues that can leave the login page
 * rendered as plain HTML after cache rebuilds.
 *
 * @param theme_config $theme The theme config object.
 * @return string SCSS content.
 */
function theme_teluai_get_main_scss_content($theme) {
    global $CFG;

    $boostdefault = $CFG->dirroot . '/theme/boost/scss/preset/default.scss';
    if (file_exists($boostdefault)) {
        return file_get_contents($boostdefault);
    }

    // Fallback for unusual Moodle installs where the preset file is unavailable.
    return "@import \"fontawesome\";\n@import \"bootstrap\";\n@import \"moodle\";";
}

/**
 * Returns the pre-SCSS content.
 *
 * Keep this empty by default so the theme only redesigns the login page and
 * does not alter dashboard/course UI through global Bootstrap variables.
 *
 * @param theme_config $theme The theme config object.
 * @return string Pre-SCSS content.
 */
function theme_teluai_get_pre_scss($theme) {
    $pre = '';

    $configpre = get_config('theme_teluai', 'scsspre');
    if (!empty($configpre)) {
        $pre .= $configpre;
    }

    return $pre;
}

/**
 * Returns extra SCSS content.
 *
 * Login and frontpage SCSS are scoped to body classes. Other pages remain
 * Boost-based unless an administrator adds custom SCSS in the theme settings.
 *
 * @param theme_config $theme The theme config object.
 * @return string Extra SCSS content.
 */
function theme_teluai_get_extra_scss($theme) {
    global $CFG;

    $extra = '';

    $loginfile = $CFG->dirroot . '/theme/teluai/scss/login.scss';
    if (file_exists($loginfile)) {
        $extra .= file_get_contents($loginfile) . "\n";
    }

    $homefile = $CFG->dirroot . '/theme/teluai/scss/home.scss';
    if (file_exists($homefile)) {
        $extra .= file_get_contents($homefile) . "\n";
    }

    $configscss = get_config('theme_teluai', 'scss');
    if (!empty($configscss)) {
        $extra .= "\n" . $configscss;
    }

    return $extra;
}

/**
 * Get the compiled CSS fallback for the theme.
 *
 * @param theme_config $theme The theme config object.
 * @return string Precompiled CSS file path.
 */
function theme_teluai_get_precompiled_css($theme) {
    global $CFG;

    return $CFG->dirroot . '/theme/boost/style/moodle.css';
}

/**
 * Serves files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_teluai_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'loginbg')) {
        $theme = theme_config::load('teluai');
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }

    send_file_not_found();
}
