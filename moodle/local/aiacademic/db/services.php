<?php
// ============================================================================
// Moodle local_aiacademic — Web Services & External Functions
// ============================================================================

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_aiacademic_chat_send_message' => array(
        'classname'     => 'local_aiacademic\external\chat_api',
        'methodname'    => 'send_message',
        'description'   => 'Send a message to AI Academic Assistant',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:usechat',
    ),
    'local_aiacademic_chat_get_history' => array(
        'classname'     => 'local_aiacademic\external\chat_api',
        'methodname'    => 'get_history',
        'description'   => 'Get chat message history for a session',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:usechat',
    ),
    'local_aiacademic_chat_get_sessions' => array(
        'classname'     => 'local_aiacademic\external\chat_api',
        'methodname'    => 'get_sessions',
        'description'   => 'Get list of chat sessions',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:usechat',
    ),
    'local_aiacademic_chat_delete_session' => array(
        'classname'     => 'local_aiacademic\external\chat_api',
        'methodname'    => 'delete_session',
        'description'   => 'Delete a chat session',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:usechat',
    ),
    'local_aiacademic_summary_generate' => array(
        'classname'     => 'local_aiacademic\external\summary_api',
        'methodname'    => 'generate',
        'description'   => 'Generate AI summary from course material',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:summarize',
    ),
    'local_aiacademic_summary_get' => array(
        'classname'     => 'local_aiacademic\external\summary_api',
        'methodname'    => 'get_summary',
        'description'   => 'Get a generated summary',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:summarize',
    ),
    'local_aiacademic_summary_list' => array(
        'classname'     => 'local_aiacademic\external\summary_api',
        'methodname'    => 'list_summaries',
        'description'   => 'List summaries for a course',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:summarize',
    ),
    'local_aiacademic_quiz_generate' => array(
        'classname'     => 'local_aiacademic\external\quiz_api',
        'methodname'    => 'generate',
        'description'   => 'Generate quiz questions using AI',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:generatequiz',
    ),
    'local_aiacademic_quiz_review_question' => array(
        'classname'     => 'local_aiacademic\external\quiz_api',
        'methodname'    => 'review_question',
        'description'   => 'Review an AI-generated question',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:reviewquiz',
    ),
    'local_aiacademic_quiz_publish' => array(
        'classname'     => 'local_aiacademic\external\quiz_api',
        'methodname'    => 'publish',
        'description'   => 'Publish approved questions to Moodle',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:publishquiz',
    ),
    'local_aiacademic_quiz_get' => array(
        'classname'     => 'local_aiacademic\external\quiz_api',
        'methodname'    => 'get_genquiz',
        'description'   => 'Get generated quiz detail',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:generatequiz',
    ),
    'local_aiacademic_quiz_list' => array(
        'classname'     => 'local_aiacademic\external\quiz_api',
        'methodname'    => 'list_genquizzes',
        'description'   => 'List generated quizzes for a course',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:generatequiz',
    ),
    'local_aiacademic_logs_get' => array(
        'classname'     => 'local_aiacademic\external\log_api',
        'methodname'    => 'get_logs',
        'description'   => 'Get AI usage logs (admin)',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:viewlogs',
    ),
    'local_aiacademic_logs_stats' => array(
        'classname'     => 'local_aiacademic\external\log_api',
        'methodname'    => 'get_stats',
        'description'   => 'Get AI usage statistics (admin)',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/aiacademic:viewlogs',
    ),
);

$services = array(
    'AI Academic Assistant Service' => array(
        'functions' => array_keys($functions),
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'aiacademic',
    ),
);
