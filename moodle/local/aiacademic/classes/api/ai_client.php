<?php
// ============================================================================
// Moodle local_aiacademic — AI Service REST API Client
// Handles communication between Moodle and FastAPI AI Service
// ============================================================================

namespace local_aiacademic\api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

class ai_client {

    /** @var string Base URL of the AI Service */
    protected $baseurl;

    /** @var string API Key for authentication */
    protected $apikey;

    /** @var int Timeout in seconds */
    protected $timeout;

    /**
     * Constructor loading configuration from admin settings.
     */
    public function __construct() {
        $this->baseurl = get_config('local_aiacademic', 'ai_service_url');
        $this->apikey = get_config('local_aiacademic', 'ai_service_api_key');
        $this->timeout = get_config('local_aiacademic', 'connection_timeout') ?: 30;

        // Fallback defaults
        if (empty($this->baseurl)) {
            $this->baseurl = 'http://ai-service:8000';
        }
    }

    /**
     * Send a POST request to the AI Service.
     *
     * @param string $endpoint Endpoint path (e.g., '/api/v1/chat')
     * @param array $payload Request payload
     * @return array Response data
     * @throws \moodle_exception If request fails
     */
    protected function post($endpoint, array $payload) {
        global $USER;

        $url = rtrim($this->baseurl, '/') . '/' . ltrim($endpoint, '/');
        
        // The AI service is a trusted internal Docker service. Moodle's default curl
        // security blocks private-network hosts, so this request must opt out.
        $curl = new \curl(array('ignoresecurity' => true));
        $curl->setHeader(array(
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apikey,
            'X-User-ID: ' . $USER->id,
            'X-Request-ID: ' . \core\uuid::generate()
        ));

        // Configure options
        $curl->setopt(array(
            'CURLOPT_TIMEOUT' => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_RETURNTRANSFER' => true
        ));

        $jsonpayload = json_encode($payload);
        $response = $curl->post($url, $jsonpayload);

        $info = $curl->get_info();
        $httpcode = isset($info['http_code']) ? $info['http_code'] : 0;

        if ($curl->get_errno()) {
            throw new \moodle_exception(
                'error_api_connection',
                'local_aiacademic',
                '',
                null,
                $curl->error
            );
        }

        $result = json_decode($response, true);

        if ($httpcode !== 200) {
            $errormsg = isset($result['error']['message']) ? $result['error']['message'] : 'HTTP Error ' . $httpcode;
            $errorcode = isset($result['error']['code']) ? $result['error']['code'] : 'API_ERROR';
            
            if ($httpcode === 429) {
                throw new \moodle_exception('error_rate_limited', 'local_aiacademic');
            }

            throw new \moodle_exception(
                'error_invalid_response',
                'local_aiacademic',
                '',
                null,
                $errormsg . ' (' . $errorcode . ')'
            );
        }

        if (empty($result) || !isset($result['success']) || !$result['success']) {
            throw new \moodle_exception('error_invalid_response', 'local_aiacademic');
        }

        return $result['data'];
    }

    /**
     * Send chat prompt to AI Academic Assistant.
     *
     * @param int $userid Moodle user ID
     * @param string $message Student question
     * @param int $courseid Course context ID
     * @param array $history Previous messages
     * @return array API Response data
     */
    public function send_chat($userid, $message, $courseid = null, array $history = array()) {
        $coursecontext = null;
        if (!empty($courseid)) {
            global $DB;
            $course = $DB->get_record('course', array('id' => $courseid));
            if ($course) {
                $coursecontext = array(
                    'course_id' => (int)$course->id,
                    'course_name' => $course->fullname,
                    'course_topic' => $course->summary ? strip_tags($course->summary) : ''
                );
            }
        }

        $model = get_config('local_aiacademic', 'default_chat_model') ?: 'llama3';

        $payload = array(
            'user_id' => (int)$userid,
            'message' => $message,
            'history' => $history,
            'course_context' => $coursecontext,
            'options' => array(
                'model' => $model
            )
        );

        return $this->post('/api/v1/chat', $payload);
    }

    /**
     * Generate material summary.
     *
     * @param int $userid Moodle user ID
     * @param int $courseid Course ID
     * @param string $filename Material file name
     * @param string $contenttype MIME type
     * @param string $base64content Base64 encoded file content
     * @return array API Response data
     */
    public function generate_summary($userid, $courseid, $filename, $contenttype, $base64content) {
        $model = get_config('local_aiacademic', 'default_summary_model') ?: 'llama3';

        $payload = array(
            'user_id' => (int)$userid,
            'course_id' => (int)$courseid,
            'material' => array(
                'filename' => $filename,
                'content_type' => $contenttype,
                'content_base64' => $base64content
            ),
            'options' => array(
                'model' => $model,
                'language' => 'auto'
            )
        );

        return $this->post('/api/v1/summarize', $payload);
    }

    /**
     * Generate quiz draft.
     *
     * @param int $userid Moodle user ID
     * @param int $courseid Course ID
     * @param string $filename Material file name
     * @param string $contenttype MIME type
     * @param string $base64content Base64 encoded file content
     * @param array $questiontypes Array of types (multichoice, truefalse, essay)
     * @param int $numquestions Number of questions
     * @param string $difficulty easy/medium/hard/mixed
     * @return array API Response data
     */
    public function generate_quiz($userid, $courseid, $filename, $contenttype, $base64content, array $questiontypes, $numquestions, $difficulty) {
        $model = get_config('local_aiacademic', 'default_quiz_model') ?: 'llama3';

        $payload = array(
            'user_id' => (int)$userid,
            'course_id' => (int)$courseid,
            'material' => array(
                'filename' => $filename,
                'content_type' => $contenttype,
                'content_base64' => $base64content
            ),
            'settings' => array(
                'question_types' => $questiontypes,
                'num_questions' => (int)$numquestions,
                'difficulty' => $difficulty
            ),
            'options' => array(
                'model' => $model
            )
        );

        return $this->post('/api/v1/generate-quiz', $payload);
    }
}
