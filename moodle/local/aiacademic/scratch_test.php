<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

$baseurl = 'http://ai-service:8000';
$apikey = 'ai_api_key_change_me_in_production';
$url = $baseurl . '/api/v1/chat';

$payload = array(
    'user_id' => 2,
    'message' => 'Hello',
    'history' => array(),
    'course_context' => null,
    'options' => array(
        'model' => 'llama3'
    )
);

echo "Sending POST to: $url\n";
echo "Payload: " . json_encode($payload) . "\n";

$curl = new \curl();
$curl->setHeader(array(
    'Content-Type: application/json',
    'X-API-Key: ' . $apikey,
    'X-User-ID: 2',
    'X-Request-ID: test-request-id'
));

$response = $curl->post($url, json_encode($payload));

$info = $curl->get_info();
echo "HTTP Code: " . (isset($info['http_code']) ? $info['http_code'] : 'N/A') . "\n";
echo "Curl Errno: " . $curl->get_errno() . "\n";
echo "Curl Error: " . $curl->error . "\n";
echo "Raw Response:\n";
var_dump($response);
