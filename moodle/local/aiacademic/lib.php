<?php
// ============================================================================
// Moodle local_aiacademic — Core library functions
// ============================================================================

defined('MOODLE_INTERNAL') || die();

/**
 * Perform custom database operations on installation.
 * This is executed when the plugin is installed for the first time.
 */
function xmldb_local_aiacademic_install() {
    global $DB;
    // Perform any custom logic after table installation.
    return true;
}

/**
 * Custom navigation menu hooks for the AI Academic Assistant.
 *
 * @param global_navigation $navigation The navigation object
 */
function local_aiacademic_extend_navigation(global_navigation $navigation) {
    global $PAGE, $USER;

    // Only add navigation nodes for logged-in, non-guest users
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Check capability to use the chat assistant
    $systemcontext = context_system::instance();
    if (has_capability('local/aiacademic:usechat', $systemcontext)) {
        // Add link to navigation sidebar
        $navigation->add(
            get_string('ai_assistant_menu', 'local_aiacademic'),
            new moodle_url('/local/aiacademic/chat.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'aiacademic_chat',
            new pix_icon('i/menu', '')
        );
    }
}

/**
 * Hook to inject AI helper elements into Course navigation.
 *
 * @param settings_navigation $navigation The settings navigation object
 * @param context $context The course context object
 */
function local_aiacademic_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;

    if ($context->contextlevel != CONTEXT_COURSE) {
        return;
    }

    // Add Lecturer features (Quiz generator)
    if (has_capability('local/aiacademic:generatequiz', $context)) {
        $node = navigation_node::create(
            get_string('ai_quiz_generator', 'local_aiacademic'),
            new moodle_url('/local/aiacademic/quiz_generator.php', array('id' => $course->id)),
            navigation_node::TYPE_SETTING,
            null,
            'aiacademic_quizgen'
        );
        $navigation->add_node($node);
    }

    // Add Student/Lecturer features (Material Summarizer)
    if (has_capability('local/aiacademic:summarize', $context)) {
        $node = navigation_node::create(
            get_string('ai_material_summarizer', 'local_aiacademic'),
            new moodle_url('/local/aiacademic/summarizer.php', array('courseid' => $course->id)),
            navigation_node::TYPE_SETTING,
            null,
            'aiacademic_summarizer'
        );
        $navigation->add_node($node);
    }

    // Add AI Chat Assistant feature
    if (has_capability('local/aiacademic:usechat', $context)) {
        $node = navigation_node::create(
            get_string('ai_assistant_menu', 'local_aiacademic'),
            new moodle_url('/local/aiacademic/chat.php', array('courseid' => $course->id)),
            navigation_node::TYPE_SETTING,
            null,
            'aiacademic_chat'
        );
        $navigation->add_node($node);
    }
}
