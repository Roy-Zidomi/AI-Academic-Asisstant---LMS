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
    if ($context->contextlevel != CONTEXT_COURSE) {
        return;
    }

    $systemcontext = context_system::instance();
    $aitools = array();

    if (has_capability('local/aiacademic:summarize', $context)) {
        $aitools[] = array(
            'label' => 'AI Summarize',
            'url' => new moodle_url('/local/aiacademic/summarizer.php', array('courseid' => $course->id)),
            'key' => 'aiacademic_summarizer',
        );
    }

    if (has_capability('local/aiacademic:usechat', $context) ||
            has_capability('local/aiacademic:usechat', $systemcontext)) {
        $aitools[] = array(
            'label' => 'AI Assistant',
            'url' => new moodle_url('/local/aiacademic/chat.php', array('courseid' => $course->id)),
            'key' => 'aiacademic_chat',
        );
    }

    if (has_capability('local/aiacademic:generatequiz', $context)) {
        $aitools[] = array(
            'label' => get_string('ai_quiz_generator', 'local_aiacademic'),
            'url' => new moodle_url('/local/aiacademic/quiz_generator.php', array('id' => $course->id)),
            'key' => 'aiacademic_quizgen',
        );
    }

    foreach ($aitools as $tool) {
        $navigation->add_node(navigation_node::create(
            $tool['label'],
            $tool['url'],
            navigation_node::TYPE_SETTING,
            null,
            $tool['key']
        ));
    }
}
