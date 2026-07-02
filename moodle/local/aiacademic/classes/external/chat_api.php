<?php
// ============================================================================
// Moodle local_aiacademic — External API for Chat
// Handles student conversations with the AI Academic Assistant
// ============================================================================

namespace local_aiacademic\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use local_aiacademic\api\ai_client;
use moodle_exception;

class chat_api extends external_api {

    /**
     * Parameter description for send_message.
     */
    public static function send_message_parameters() {
        return new external_function_parameters(array(
            'sessionid' => new external_value(PARAM_INT, 'Session ID (0 for new session)', VALUE_REQUIRED),
            'courseid'  => new external_value(PARAM_INT, 'Course ID context', VALUE_REQUIRED),
            'message'   => new external_value(PARAM_RAW, 'The student question', VALUE_REQUIRED)
        ));
    }

    /**
     * Send a message to the AI assistant.
     */
    public static function send_message($sessionid, $courseid, $message) {
        global $DB, $USER;

        // Validate parameters
        $params = self::validate_parameters(self::send_message_parameters(), array(
            'sessionid' => $sessionid,
            'courseid'  => $courseid,
            'message'   => $message
        ));

        // Capability check
        $context = ($params['courseid'] > 0) ? \context_course::instance($params['courseid']) : \context_system::instance();
        self::validate_context($context);
        require_capability('local/aiacademic:usechat', $context);

        $sessionid = $params['sessionid'];
        $courseid = $params['courseid'];
        $message = trim($params['message']);

        if (strlen($message) < 3) {
            throw new moodle_exception('validation_error', 'local_aiacademic', '', null, 'Message too short.');
        }

        // 1. Manage chat session (create if new)
        if ($sessionid === 0) {
            $session = new \stdClass();
            $session->userid = $USER->id;
            $session->courseid = $courseid ?: null;
            // Generate title from first 30 characters of message
            $session->title = \core_text::substr($message, 0, 30) . (strlen($message) > 30 ? '...' : '');
            $session->status = 1;
            $session->timecreated = time();
            $session->timemodified = time();
            $sessionid = $DB->insert_record('local_aiacademic_chats', $session);
            $session->id = $sessionid;
        } else {
            // Verify session belongs to user
            $session = $DB->get_record('local_aiacademic_chats', array('id' => $sessionid, 'userid' => $USER->id));
            if (!$session) {
                throw new moodle_exception('error_access_denied', 'local_aiacademic');
            }
        }

        // 2. Fetch history (last 5 messages) for prompt context
        $history = array();
        $records = $DB->get_records_select(
            'local_aiacademic_messages',
            'sessionid = :sessionid',
            array('sessionid' => $sessionid),
            'timecreated DESC',
            'role, content',
            0,
            10
        );
        // Reverse to chronological order
        $records = array_reverse($records);
        foreach ($records as $r) {
            $history[] = array(
                'role' => $r->role,
                'content' => $r->content
            );
        }

        // 3. Save student message
        $usermsg = new \stdClass();
        $usermsg->sessionid = $sessionid;
        $usermsg->role = 'user';
        $usermsg->content = $message;
        $usermsg->timecreated = time();
        $DB->insert_record('local_aiacademic_messages', $usermsg);

        // Update session modification time
        $session->timemodified = time();
        $DB->update_record('local_aiacademic_chats', $session);

        // 4. Call AI Service via client
        $client = new ai_client();
        $status = 'success';
        $errormsg = null;
        $response = null;

        try {
            $response = $client->send_chat($USER->id, $message, $courseid, $history);
        } catch (\Exception $e) {
            $status = 'error';
            $errormsg = $e->getMessage();
            
            // Log error message as assistant response in DB to keep conversation flow
            $assistantmsg = new \stdClass();
            $assistantmsg->sessionid = $sessionid;
            $assistantmsg->role = 'assistant';
            $assistantmsg->content = get_string('error_api_connection', 'local_aiacademic') . ' (' . $errormsg . ')';
            $assistantmsg->timecreated = time();
            $DB->insert_record('local_aiacademic_messages', $assistantmsg);

            // Re-throw to inform frontend
            throw $e;
        }

        // 5. Save assistant response
        $assistantmsg = new \stdClass();
        $assistantmsg->sessionid = $sessionid;
        $assistantmsg->role = 'assistant';
        $assistantmsg->content = $response['response'];
        $assistantmsg->model_used = $response['model'];
        $assistantmsg->tokens_used = isset($response['tokens']['total']) ? $response['tokens']['total'] : 0;
        $assistantmsg->response_time = isset($response['response_time_seconds']) ? $response['response_time_seconds'] : 0.0;
        $assistantmsg->timecreated = time();
        $DB->insert_record('local_aiacademic_messages', $assistantmsg);

        // 6. Save log
        $log = new \stdClass();
        $log->userid = $USER->id;
        $log->feature_type = 'chat';
        $log->model_used = $response['model'];
        $log->input_tokens = isset($response['tokens']['input']) ? $response['tokens']['input'] : 0;
        $log->output_tokens = isset($response['tokens']['output']) ? $response['tokens']['output'] : 0;
        $log->response_time_ms = $assistantmsg->response_time * 1000;
        $log->status = $status;
        $log->error_message = $errormsg;
        $log->ip_address = getremoteaddr();
        $log->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
        $log->timecreated = time();
        $DB->insert_record('local_aiacademic_logs', $log);

        // Return structured result
        return array(
            'session_id' => $sessionid,
            'response' => $assistantmsg->content,
            'model' => $assistantmsg->model_used,
            'response_time' => $assistantmsg->response_time
        );
    }

    /**
     * Return description for send_message.
     */
    public static function send_message_returns() {
        return new external_single_structure(array(
            'session_id' => new external_value(PARAM_INT, 'The chat session ID'),
            'response'   => new external_value(PARAM_RAW, 'AI response content'),
            'model'      => new external_value(PARAM_TEXT, 'Model used for generation'),
            'response_time' => new external_value(PARAM_FLOAT, 'Inference time in seconds')
        ));
    }

    /**
     * Parameter description for get_history.
     */
    public static function get_history_parameters() {
        return new external_function_parameters(array(
            'sessionid' => new external_value(PARAM_INT, 'Session ID', VALUE_REQUIRED),
            'page'      => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
            'perpage'   => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 20)
        ));
    }

    /**
     * Get conversation history.
     */
    public static function get_history($sessionid, $page = 1, $perpage = 20) {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_history_parameters(), array(
            'sessionid' => $sessionid,
            'page' => $page,
            'perpage' => $perpage
        ));

        $sessionid = $params['sessionid'];
        $page = max(1, $params['page']);
        $perpage = max(1, min(100, $params['perpage']));
        $offset = ($page - 1) * $perpage;

        // Verify ownership
        $session = $DB->get_record('local_aiacademic_chats', array('id' => $sessionid, 'userid' => $USER->id));
        if (!$session) {
            throw new moodle_exception('error_access_denied', 'local_aiacademic');
        }

        // Capability check
        $context = ($session->courseid) ? \context_course::instance($session->courseid) : \context_system::instance();
        self::validate_context($context);
        require_capability('local/aiacademic:usechat', $context);

        // Fetch messages
        $messages = $DB->get_records(
            'local_aiacademic_messages',
            array('sessionid' => $sessionid),
            'timecreated ASC',
            'id, role, content, timecreated',
            $offset,
            $perpage
        );

        $result = array();
        foreach ($messages as $m) {
            $result[] = array(
                'id' => (int)$m->id,
                'role' => $m->role,
                'content' => $m->content,
                'timecreated' => (int)$m->timecreated
            );
        }

        return $result;
    }

    /**
     * Return description for get_history.
     */
    public static function get_history_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id' => new external_value(PARAM_INT, 'Message ID'),
                'role' => new external_value(PARAM_TEXT, 'Sender role (user/assistant)'),
                'content' => new external_value(PARAM_RAW, 'Message content'),
                'timecreated' => new external_value(PARAM_INT, 'Unix timestamp')
            ))
        );
    }

    /**
     * Parameter description for get_sessions.
     */
    public static function get_sessions_parameters() {
        return new external_function_parameters(array(
            'courseid' => new external_value(PARAM_INT, 'Course filter (0 for general/all)', VALUE_DEFAULT, 0),
            'page'     => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
            'perpage'  => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 10)
        ));
    }

    /**
     * List user chat sessions.
     */
    public static function get_sessions($courseid = 0, $page = 1, $perpage = 10) {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_sessions_parameters(), array(
            'courseid' => $courseid,
            'page' => $page,
            'perpage' => $perpage
        ));

        $context = ($params['courseid'] > 0) ? \context_course::instance($params['courseid']) : \context_system::instance();
        self::validate_context($context);
        require_capability('local/aiacademic:usechat', $context);

        $courseid = $params['courseid'];
        $page = max(1, $params['page']);
        $perpage = max(1, min(50, $params['perpage']));
        $offset = ($page - 1) * $perpage;

        $select = 'userid = :userid AND status = 1';
        $selectparams = array('userid' => $USER->id);

        if ($courseid > 0) {
            $select .= ' AND courseid = :courseid';
            $selectparams['courseid'] = $courseid;
        }

        $sessions = $DB->get_records_select(
            'local_aiacademic_chats',
            $select,
            $selectparams,
            'timemodified DESC',
            'id, title, courseid, timecreated, timemodified',
            $offset,
            $perpage
        );

        $result = array();
        foreach ($sessions as $s) {
            $coursename = '';
            if ($s->courseid) {
                $course = $DB->get_record('course', array('id' => $s->courseid), 'fullname');
                if ($course) {
                    $coursename = $course->fullname;
                }
            }

            $result[] = array(
                'id' => (int)$s->id,
                'title' => $s->title ?: 'New Conversation',
                'courseid' => $s->courseid ? (int)$s->courseid : 0,
                'coursename' => $coursename,
                'timecreated' => (int)$s->timecreated,
                'timemodified' => (int)$s->timemodified
            );
        }

        return $result;
    }

    /**
     * Return description for get_sessions.
     */
    public static function get_sessions_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id' => new external_value(PARAM_INT, 'Session ID'),
                'title' => new external_value(PARAM_TEXT, 'Session title'),
                'courseid' => new external_value(PARAM_INT, 'Course ID context'),
                'coursename' => new external_value(PARAM_TEXT, 'Course name'),
                'timecreated' => new external_value(PARAM_INT, 'Created time'),
                'timemodified' => new external_value(PARAM_INT, 'Modified time')
            ))
        );
    }

    /**
     * Parameter description for delete_session.
     */
    public static function delete_session_parameters() {
        return new external_function_parameters(array(
            'sessionid' => new external_value(PARAM_INT, 'Session ID to delete', VALUE_REQUIRED)
        ));
    }

    /**
     * Soft delete/archive a chat session.
     */
    public static function delete_session($sessionid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::delete_session_parameters(), array(
            'sessionid' => $sessionid
        ));

        $sessionid = $params['sessionid'];

        $session = $DB->get_record('local_aiacademic_chats', array('id' => $sessionid, 'userid' => $USER->id));
        if (!$session) {
            throw new moodle_exception('error_access_denied', 'local_aiacademic');
        }

        // Capability check
        $context = ($session->courseid) ? \context_course::instance($session->courseid) : \context_system::instance();
        self::validate_context($context);
        require_capability('local/aiacademic:usechat', $context);

        // Soft delete: status = 0
        $session->status = 0;
        $session->timemodified = time();
        $DB->update_record('local_aiacademic_chats', $session);

        return array(
            'deleted' => true,
            'sessionid' => $sessionid
        );
    }

    /**
     * Return description for delete_session.
     */
    public static function delete_session_returns() {
        return new external_single_structure(array(
            'deleted' => new external_value(PARAM_BOOL, 'Delete operation status'),
            'sessionid' => new external_value(PARAM_INT, 'The deleted session ID')
        ));
    }
}
