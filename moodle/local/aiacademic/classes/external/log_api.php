<?php
// ============================================================================
// Moodle local_aiacademic — External API for Logs
// Handles AI usage logging queries for Administrators
// ============================================================================

namespace local_aiacademic\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use moodle_exception;

class log_api extends external_api {

    /**
     * Parameter description for get_logs.
     */
    public static function get_logs_parameters() {
        return new external_function_parameters(array(
            'feature_type' => new external_value(PARAM_TEXT, 'chat, summary, or quiz_generation (empty = all)', VALUE_DEFAULT, ''),
            'status'       => new external_value(PARAM_TEXT, 'success or error (empty = all)', VALUE_DEFAULT, ''),
            'page'         => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
            'perpage'      => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 20)
        ));
    }

    /**
     * Fetch AI log list with optional filters.
     */
    public static function get_logs($feature_type = '', $status = '', $page = 1, $perpage = 20) {
        global $DB;

        $params = self::validate_parameters(self::get_logs_parameters(), array(
            'feature_type' => $feature_type,
            'status' => $status,
            'page' => $page,
            'perpage' => $perpage
        ));

        // Validate administrative access context
        $systemcontext = \context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local_aiacademic:viewlogs', $systemcontext);

        $page = max(1, $params['page']);
        $perpage = max(1, min(100, $params['perpage']));
        $offset = ($page - 1) * $perpage;

        $select = '1=1';
        $selectparams = array();

        if (!empty($params['feature_type'])) {
            $select .= ' AND feature_type = :feature';
            $selectparams['feature'] = $params['feature_type'];
        }

        if (!empty($params['status'])) {
            $select .= ' AND status = :status';
            $selectparams['status'] = $params['status'];
        }

        $logs = $DB->get_records_select(
            'local_aiacademic_logs',
            $select,
            $selectparams,
            'timecreated DESC',
            'id, userid, feature_type, model_used, input_tokens, output_tokens, response_time_ms, status, error_message, ip_address, timecreated',
            $offset,
            $perpage
        );

        $result = array();
        foreach ($logs as $l) {
            $username = 'Unknown';
            $user = $DB->get_record('user', array('id' => $l->userid), 'username, firstname, lastname');
            if ($user) {
                $username = fullname($user) . ' (' . $user->username . ')';
            }

            $result[] = array(
                'id' => (int)$l->id,
                'username' => $username,
                'feature_type' => $l->feature_type,
                'model_used' => $l->model_used ?: 'unknown',
                'input_tokens' => $l->input_tokens ? (int)$l->input_tokens : 0,
                'output_tokens' => $l->output_tokens ? (int)$l->output_tokens : 0,
                'response_time_ms' => (float)$l->response_time_ms,
                'status' => $l->status,
                'error_message' => $l->error_message ?: '',
                'ip_address' => $l->ip_address ?: '',
                'timecreated' => (int)$l->timecreated
            );
        }

        return $result;
    }

    /**
     * Return description for get_logs.
     */
    public static function get_logs_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id'               => new external_value(PARAM_INT, 'Log ID'),
                'username'         => new external_value(PARAM_TEXT, 'Full name and username of user'),
                'feature_type'     => new external_value(PARAM_TEXT, 'Feature chat/summary/quiz_generation'),
                'model_used'       => new external_value(PARAM_TEXT, 'Model name'),
                'input_tokens'     => new external_value(PARAM_INT, 'Input token count'),
                'output_tokens'    => new external_value(PARAM_INT, 'Output token count'),
                'response_time_ms' => new external_value(PARAM_FLOAT, 'Latency in ms'),
                'status'           => new external_value(PARAM_TEXT, 'success or error'),
                'error_message'    => new external_value(PARAM_RAW, 'Error details if failed'),
                'ip_address'       => new external_value(PARAM_TEXT, 'Client IP address'),
                'timecreated'      => new external_value(PARAM_INT, 'Unix timestamp of action')
            ))
        );
    }

    /**
     * Parameter description for get_stats.
     */
    public static function get_stats_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Fetch aggregated statistics for admin dashboard.
     */
    public static function get_stats() {
        global $DB;

        $systemcontext = \context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local_aiacademic:viewlogs', $systemcontext);

        $totalrequests = $DB->count_records('local_aiacademic_logs');
        $successcount = $DB->count_records('local_aiacademic_logs', array('status' => 'success'));
        $errorcount = $DB->count_records('local_aiacademic_logs', array('status' => 'error'));

        // Query average response time
        $sql = "SELECT AVG(response_time_ms) as avg_time FROM {local_aiacademic_logs}";
        $avgtime = $DB->get_field_sql($sql);

        // Query sum of tokens
        $sql = "SELECT SUM(input_tokens) as total_in, SUM(output_tokens) as total_out FROM {local_aiacademic_logs}";
        $tokens = $DB->get_record_sql($sql);

        return array(
            'total_requests' => (int)$totalrequests,
            'success_count' => (int)$successcount,
            'error_count' => (int)$errorcount,
            'avg_response_time_ms' => $avgtime ? (float)$avgtime : 0.0,
            'total_input_tokens' => $tokens && $tokens->total_in ? (int)$tokens->total_in : 0,
            'total_output_tokens' => $tokens && $tokens->total_out ? (int)$tokens->total_out : 0
        );
    }

    /**
     * Return description for get_stats.
     */
    public static function get_stats_returns() {
        return new external_single_structure(array(
            'total_requests'       => new external_value(PARAM_INT, 'Total calls'),
            'success_count'        => new external_value(PARAM_INT, 'Successful calls'),
            'error_count'          => new external_value(PARAM_INT, 'Failed calls'),
            'avg_response_time_ms' => new external_value(PARAM_FLOAT, 'Average latency in milliseconds'),
            'total_input_tokens'   => new external_value(PARAM_INT, 'Sum of input tokens'),
            'total_output_tokens'  => new external_value(PARAM_INT, 'Sum of output tokens')
        ));
    }
}
