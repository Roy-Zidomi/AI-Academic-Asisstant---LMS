<?php
// ============================================================================
// Moodle local_aiacademic — Language Strings (English)
// ============================================================================

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Academic Assistant';
$string['ai_assistant_menu'] = 'AI Academic Assistant';
$string['ai_quiz_generator'] = 'AI Quiz Generator';
$string['ai_material_summarizer'] = 'AI Material Summarizer';

// Admin Settings
$string['ai_service_url'] = 'AI Service URL';
$string['ai_service_url_desc'] = 'The base URL of the FastAPI AI Service (e.g., http://ai-service:8000)';
$string['ai_service_api_key'] = 'AI Service API Key';
$string['ai_service_api_key_desc'] = 'The authentication key used to call the AI Service';
$string['default_chat_model'] = 'Default Chat Model';
$string['default_chat_model_desc'] = 'The LLM model used for the Chat Assistant (e.g., llama3)';
$string['default_summary_model'] = 'Default Summary Model';
$string['default_summary_model_desc'] = 'The LLM model used for summaries (e.g., llama3)';
$string['default_quiz_model'] = 'Default Quiz Model';
$string['default_quiz_model_desc'] = 'The LLM model used for Quiz Generation (e.g., llama3)';
$string['connection_timeout'] = 'Connection Timeout';
$string['connection_timeout_desc'] = 'Timeout limit in seconds when communicating with the AI service';
$string['rate_limit_chat'] = 'Chat Rate Limit';
$string['rate_limit_chat_desc'] = 'Maximum number of chat requests allowed per user per hour';

// Chat Assistant UI
$string['chat_title'] = 'AI Academic Assistant';
$string['chat_welcome'] = 'Welcome! I am your AI Academic Assistant. Ask me any questions related to your courses.';
$string['chat_placeholder'] = 'Type your academic question here...';
$string['chat_send'] = 'Send';
$string['chat_disclaimer'] = 'Disclaimer: Responses are AI-generated. Verify important facts with your lecturer.';
$string['chat_history'] = 'Chat History';
$string['chat_new_session'] = 'New Conversation';
$string['chat_clear_confirm'] = 'Are you sure you want to delete this conversation?';

// Summarizer UI
$string['summary_title'] = 'AI Material Summarizer';
$string['summary_generate'] = 'Generate Summary';
$string['summary_regenerate'] = 'Force Regenerate';
$string['summary_executive'] = 'Executive Summary';
$string['summary_key_points'] = 'Key Points';
$string['summary_concepts'] = 'Important Concepts';
$string['summary_glossary'] = 'Glossary';
$string['summary_study_guide'] = 'Study Guide';
$string['summary_not_found'] = 'No summary found for this material.';
$string['summary_loading'] = 'Extracting content and generating summary... Please wait.';

// Quiz Generator UI
$string['quiz_generator_title'] = 'AI Quiz Generator';
$string['quiz_difficulty'] = 'Difficulty Level';
$string['quiz_difficulty_easy'] = 'Easy';
$string['quiz_difficulty_medium'] = 'Medium';
$string['quiz_difficulty_hard'] = 'Hard';
$string['quiz_difficulty_mixed'] = 'Mixed';
$string['quiz_num_questions'] = 'Number of Questions';
$string['quiz_types'] = 'Question Types';
$string['quiz_type_mcq'] = 'Multiple Choice (MCQ)';
$string['quiz_type_tf'] = 'True / False';
$string['quiz_type_essay'] = 'Essay';
$string['quiz_generate_btn'] = 'Generate Quiz Draft';
$string['quiz_generating'] = 'Analyzing learning material and generating questions... This may take up to a minute.';
$string['quiz_review_title'] = 'Review Generated Questions';
$string['quiz_publish_btn'] = 'Publish to Question Bank';
$string['quiz_publish_success'] = 'Questions successfully published!';
$string['quiz_approve'] = 'Approve';
$string['quiz_reject'] = 'Reject';
$string['quiz_edit'] = 'Edit';

// Error Messages
$string['error_api_connection'] = 'Could not connect to the AI Service.';
$string['error_rate_limited'] = 'Rate limit exceeded. Please try again in a few minutes.';
$string['error_invalid_response'] = 'Received an invalid response format from the AI Service.';
$string['error_access_denied'] = 'You do not have permission to access this AI feature.';
