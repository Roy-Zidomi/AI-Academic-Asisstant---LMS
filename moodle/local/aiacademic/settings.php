<?php
// ============================================================================
// Moodle local_aiacademic — Administration settings
// ============================================================================

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create settings page category
    $settings = new admin_settingpage('local_aiacademic', get_string('pluginname', 'local_aiacademic'));

    // AI Service URL setting
    $settings->add(new admin_setting_configtext(
        'local_aiacademic/ai_service_url',
        get_string('ai_service_url', 'local_aiacademic'),
        get_string('ai_service_url_desc', 'local_aiacademic'),
        'http://ai-service:8000',
        PARAM_URL
    ));

    // AI Service API Key setting
    $settings->add(new admin_setting_configpasswordunmask(
        'local_aiacademic/ai_service_api_key',
        get_string('ai_service_api_key', 'local_aiacademic'),
        get_string('ai_service_api_key_desc', 'local_aiacademic'),
        'ai_api_key_change_me_in_production'
    ));

    // AI Default Chat Model setting
    $settings->add(new admin_setting_configtext(
        'local_aiacademic/default_chat_model',
        get_string('default_chat_model', 'local_aiacademic'),
        get_string('default_chat_model_desc', 'local_aiacademic'),
        'llama3',
        PARAM_TEXT
    ));

    // AI Default Summary Model setting
    $settings->add(new admin_setting_configtext(
        'local_aiacademic/default_summary_model',
        get_string('default_summary_model', 'local_aiacademic'),
        get_string('default_summary_model_desc', 'local_aiacademic'),
        'llama3',
        PARAM_TEXT
    ));

    // AI Default Quiz Model setting
    $settings->add(new admin_setting_configtext(
        'local_aiacademic/default_quiz_model',
        get_string('default_quiz_model', 'local_aiacademic'),
        get_string('default_quiz_model_desc', 'local_aiacademic'),
        'llama3',
        PARAM_TEXT
    ));

    // Connection Timeout (seconds)
    $settings->add(new admin_setting_configduration(
        'local_aiacademic/connection_timeout',
        get_string('connection_timeout', 'local_aiacademic'),
        get_string('connection_timeout_desc', 'local_aiacademic'),
        30
    ));

    // Chat rate limiting setting (requests per hour)
    $settings->add(new admin_setting_configtext(
        'local_aiacademic/rate_limit_chat',
        get_string('rate_limit_chat', 'local_aiacademic'),
        get_string('rate_limit_chat_desc', 'local_aiacademic'),
        20,
        PARAM_INT
    ));

    // Add settings page to administration tree under local plugins
    $ADMIN->add('localplugins', $settings);
}
