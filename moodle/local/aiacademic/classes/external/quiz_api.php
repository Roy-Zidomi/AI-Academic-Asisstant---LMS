<?php
// ============================================================================
// Moodle local_aiacademic — External API for Quiz Generator
// Handles draft generation, question review, and publishing to Moodle
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

class quiz_api extends external_api {

    /**
     * Parameter description for generate.
     */
    public static function generate_parameters() {
        return new external_function_parameters(array(
            'courseid'       => new external_value(PARAM_INT, 'Course ID context', VALUE_REQUIRED),
            'cmid'           => new external_value(PARAM_INT, 'Course Module ID of the file resource', VALUE_REQUIRED),
            'question_types' => new external_value(PARAM_TEXT, 'Comma-separated question types (multichoice,truefalse,essay)', VALUE_REQUIRED),
            'num_questions'  => new external_value(PARAM_INT, 'Number of questions to generate', VALUE_REQUIRED),
            'difficulty'     => new external_value(PARAM_TEXT, 'easy, medium, hard, mixed', VALUE_REQUIRED)
        ));
    }

    /**
     * Generate a draft quiz using AI.
     */
    public static function generate($courseid, $cmid, $question_types, $num_questions, $difficulty) {
        global $DB, $USER;

        $params = self::validate_parameters(self::generate_parameters(), array(
            'courseid' => $courseid,
            'cmid' => $cmid,
            'question_types' => $question_types,
            'num_questions' => $num_questions,
            'difficulty' => $difficulty
        ));

        $coursecontext = \context_course::instance($params['courseid']);
        self::validate_context($coursecontext);
        require_capability('local/aiacademic:generatequiz', $coursecontext);

        $courseid = $params['courseid'];
        $cmid = $params['cmid'];
        $qtypes = array_map('trim', explode(',', $params['question_types']));
        $num = $params['num_questions'];
        $difficulty = $params['difficulty'];

        // Validate count
        if ($num < 1 || $num > 50) {
            throw new moodle_exception('validation_error', 'local_aiacademic', '', null, 'Number of questions must be between 1 and 50.');
        }

        // 1. Fetch file content from Moodle storage
        $cm = get_coursemodule_from_id('resource', $cmid, $courseid, false, MUST_EXIST);
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        if (empty($files)) {
            throw new moodle_exception('filenotfound', 'error');
        }
        $file = reset($files);
        $filename = $file->get_filename();
        $contenttype = $file->get_mimetype();
        $filebytes = $file->get_content();

        // 2. Create pending batch record
        $batch = new \stdClass();
        $batch->userid = $USER->id;
        $batch->courseid = $courseid;
        $batch->cmid = $cmid;
        $batch->source_filename = $filename;
        $batch->difficulty = $difficulty;
        $batch->requested_count = $num;
        $batch->question_types = $params['question_types'];
        $batch->status = 0; // pending
        $batch->timecreated = time();
        $batchid = $DB->insert_record('local_aiacademic_genquizzes', $batch);

        // 3. Call AI Service client
        $client = new ai_client();
        $status = 'success';
        $errormsg = null;
        $result = null;

        try {
            $base64 = base64_encode($filebytes);
            $result = $client->generate_quiz($USER->id, $courseid, $filename, $contenttype, $base64, $qtypes, $num, $difficulty);
        } catch (\Exception $e) {
            $status = 'error';
            $errormsg = $e->getMessage();

            $batch->id = $batchid;
            $batch->status = 4; // failed
            $DB->update_record('local_aiacademic_genquizzes', $batch);
            throw $e;
        }

        // 4. Save generated questions
        $questions = array();
        foreach ($result['questions'] as $q) {
            $question = new \stdClass();
            $question->genquizid = $batchid;
            $question->qtype = $q['type'];
            $question->question_text = $q['question'];
            $question->options_json = isset($q['options']) ? json_encode($q['options']) : null;
            $question->correct_answer = (string)$q['correct_answer'];
            $question->explanation = $q['explanation'];
            $question->difficulty = $q['difficulty'];
            $question->review_status = 0; // pending review
            
            // Essay-specific
            if (isset($q['expected_answer_guidelines'])) {
                $question->reviewer_comment = $q['expected_answer_guidelines']; // Store temporarily here
            }

            $question->timecreated = time();
            $question->timemodified = time();
            $qid = $DB->insert_record('local_aiacademic_questions', $question);

            // Fetch to return
            $qrecord = $DB->get_record('local_aiacademic_questions', array('id' => $qid));
            $questions[] = array(
                'id' => (int)$qrecord->id,
                'type' => $qrecord->qtype,
                'question' => $qrecord->question_text,
                'options' => $qrecord->options_json ? json_decode($qrecord->options_json, true) : array(),
                'correct_answer' => $qrecord->correct_answer,
                'explanation' => $qrecord->explanation,
                'difficulty' => $qrecord->difficulty,
                'review_status' => (int)$qrecord->review_status
            );
        }

        // Update batch record status
        $batch->id = $batchid;
        $batch->generated_count = count($questions);
        $batch->model_used = $result['metadata']['model'];
        $batch->generation_time = $result['metadata']['generation_time_seconds'];
        $batch->status = 1; // generated
        $DB->update_record('local_aiacademic_genquizzes', $batch);

        // 5. Log operation
        $log = new \stdClass();
        $log->userid = $USER->id;
        $log->feature_type = 'quiz_generation';
        $log->model_used = $batch->model_used;
        $log->input_tokens = 0; // Configured at service side
        $log->output_tokens = 0;
        $log->response_time_ms = $batch->generation_time * 1000;
        $log->status = $status;
        $log->error_message = $errormsg;
        $log->ip_address = getremoteaddr();
        $log->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
        $log->timecreated = time();
        $DB->insert_record('local_aiacademic_logs', $log);

        return array(
            'genquiz_id' => $batchid,
            'questions' => $questions
        );
    }

    /**
     * Return description for generate.
     */
    public static function generate_returns() {
        return new external_single_structure(array(
            'genquiz_id' => new external_value(PARAM_INT, 'Batch quiz generation ID'),
            'questions'  => new external_multiple_structure(
                new external_single_structure(array(
                    'id' => new external_value(PARAM_INT, 'Question ID'),
                    'type' => new external_value(PARAM_TEXT, 'Question type (multichoice,truefalse,essay)'),
                    'question' => new external_value(PARAM_RAW, 'Question text'),
                    'options' => new external_multiple_structure(
                        new external_value(PARAM_RAW, 'Option'),
                        'Answer options',
                        VALUE_OPTIONAL
                    ),
                    'correct_answer' => new external_value(PARAM_RAW, 'Correct answer key'),
                    'explanation' => new external_value(PARAM_RAW, 'Explanation text'),
                    'difficulty' => new external_value(PARAM_TEXT, 'Question difficulty'),
                    'review_status' => new external_value(PARAM_INT, '0=pending, 1=approved, 2=rejected, 3=edited')
                ))
            )
        ));
    }

    /**
     * Parameter description for review_question.
     */
    public static function review_question_parameters() {
        return new external_function_parameters(array(
            'questionid'            => new external_value(PARAM_INT, 'Generated question ID', VALUE_REQUIRED),
            'action'                => new external_value(PARAM_TEXT, 'approve, reject, edit', VALUE_REQUIRED),
            'edited_question'       => new external_value(PARAM_RAW, 'Edited question text', VALUE_DEFAULT, null),
            'edited_options'        => new external_value(PARAM_RAW, 'Edited options (JSON string)', VALUE_DEFAULT, null),
            'edited_correct_answer' => new external_value(PARAM_RAW, 'Edited correct answer key', VALUE_DEFAULT, null),
            'edited_explanation'    => new external_value(PARAM_RAW, 'Edited explanation', VALUE_DEFAULT, null),
            'comment'               => new external_value(PARAM_RAW, 'Reviewer comment', VALUE_DEFAULT, null)
        ));
    }

    /**
     * Review / Edit a single generated question.
     */
    public static function review_question($questionid, $action, $edited_question = null, $edited_options = null, $edited_correct_answer = null, $edited_explanation = null, $comment = null) {
        global $DB;

        $params = self::validate_parameters(self::review_question_parameters(), array(
            'questionid' => $questionid,
            'action' => $action,
            'edited_question' => $edited_question,
            'edited_options' => $edited_options,
            'edited_correct_answer' => $edited_correct_answer,
            'edited_explanation' => $edited_explanation,
            'comment' => $comment
        ));

        $qrecord = $DB->get_record('local_aiacademic_questions', array('id' => $params['questionid']), '*', MUST_EXIST);
        $batch = $DB->get_record('local_aiacademic_genquizzes', array('id' => $qrecord->genquizid), '*', MUST_EXIST);

        $coursecontext = \context_course::instance($batch->courseid);
        self::validate_context($coursecontext);
        require_capability('local/aiacademic:reviewquiz', $coursecontext);

        $action = $params['action'];
        
        if ($action === 'approve') {
            $qrecord->review_status = 1;
        } else if ($action === 'reject') {
            $qrecord->review_status = 2;
        } else if ($action === 'edit') {
            $qrecord->review_status = 3;
            if ($params['edited_question'] !== null) {
                $qrecord->question_text = $params['edited_question'];
            }
            if ($params['edited_options'] !== null) {
                $qrecord->options_json = $params['edited_options'];
            }
            if ($params['edited_correct_answer'] !== null) {
                $qrecord->correct_answer = $params['edited_correct_answer'];
            }
            if ($params['edited_explanation'] !== null) {
                $qrecord->explanation = $params['edited_explanation'];
            }
        }

        $qrecord->reviewer_comment = $params['comment'];
        $qrecord->timemodified = time();
        $DB->update_record('local_aiacademic_questions', $qrecord);

        return array(
            'success' => true,
            'questionid' => (int)$qrecord->id,
            'review_status' => (int)$qrecord->review_status
        );
    }

    /**
     * Return description for review_question.
     */
    public static function review_question_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'Review success'),
            'questionid' => new external_value(PARAM_INT, 'The question ID reviewed'),
            'review_status' => new external_value(PARAM_INT, 'New status code')
        ));
    }

    /**
     * Parameter description for publish.
     */
    public static function publish_parameters() {
        return new external_function_parameters(array(
            'genquiz_id'     => new external_value(PARAM_INT, 'Batch generated quiz ID', VALUE_REQUIRED),
            'target_type'    => new external_value(PARAM_TEXT, 'questionbank or quiz', VALUE_DEFAULT, 'questionbank'),
            'target_quiz_id' => new external_value(PARAM_INT, 'Moodle Quiz ID to add questions to (if type=quiz)', VALUE_DEFAULT, 0),
            'category_name'  => new external_value(PARAM_TEXT, 'Category name in question bank', VALUE_DEFAULT, '')
        ));
    }

    /**
     * Import approved questions into Moodle Question Bank.
     */
    public static function publish($genquiz_id, $target_type = 'questionbank', $target_quiz_id = 0, $category_name = '') {
        global $DB;

        $params = self::validate_parameters(self::publish_parameters(), array(
            'genquiz_id' => $genquiz_id,
            'target_type' => $target_type,
            'target_quiz_id' => $target_quiz_id,
            'category_name' => $category_name
        ));

        $batch = $DB->get_record('local_aiacademic_genquizzes', array('id' => $params['genquiz_id']), '*', MUST_EXIST);
        $coursecontext = \context_course::instance($batch->courseid);
        self::validate_context($coursecontext);
        require_capability('local/aiacademic:publishquiz', $coursecontext);

        // Fetch all approved/edited questions
        $questions = $DB->get_records_select(
            'local_aiacademic_questions',
            'genquizid = :genquizid AND (review_status = 1 OR review_status = 3)',
            array('genquizid' => $batch->id)
        );

        if (empty($questions)) {
            throw new moodle_exception('validation_error', 'local_aiacademic', '', null, 'No approved questions to publish.');
        }

        // Get or create Question Bank category
        $categoryname = $params['category_name'] ?: 'AI Generated — ' . $batch->source_filename;
        $category = $DB->get_record('question_categories', array(
            'contextid' => $coursecontext->id,
            'name' => $categoryname
        ));

        if (!$category) {
            $category = new \stdClass();
            $category->name = $categoryname;
            $category->contextid = $coursecontext->id;
            $category->info = 'AI-generated questions from file ' . $batch->source_filename;
            $category->infoformat = FORMAT_HTML;
            $category->stamp = make_unique_id_code();
            $category->parent = 0;
            $category->sortorder = 999;
            $category->id = $DB->insert_record('question_categories', $category);
        }

        $publishedcount = 0;
        $moodlequestionids = array();

        foreach ($questions as $q) {
            // Check if already published
            if ($q->moodle_questionid) {
                continue;
            }

            // Create Moodle core question structure
            $question = new \stdClass();
            $question->category = $category->id;
            $question->name = 'AIQ_' . substr(strip_tags($q->question_text), 0, 30);
            $question->questiontext = $q->question_text;
            $question->questiontextformat = FORMAT_HTML;
            $question->generalfeedback = $q->explanation;
            $question->generalfeedbackformat = FORMAT_HTML;
            $question->defaultmark = 1;
            $question->penalty = 0.3333333;
            $question->stamp = make_unique_id_code();
            $question->version = make_unique_id_code();
            $question->timecreated = time();
            $question->timemodified = time();

            // Set question type details
            if ($q->qtype === 'multichoice') {
                $question->qtype = 'multichoice';
                $qid = $DB->insert_record('question', $question);

                // Add multichoice specific configurations
                $mc = new \stdClass();
                $mc->questionid = $qid;
                $mc->single = 1; // single answer correct
                $mc->shuffleanswers = 1;
                $mc->correctfeedback = 'Correct answer!';
                $mc->correctfeedbackformat = FORMAT_HTML;
                $mc->incorrectfeedback = 'Incorrect.';
                $mc->incorrectfeedbackformat = FORMAT_HTML;
                $DB->insert_record('qtype_multichoice_options', $mc);

                // Insert answer options
                $options = json_decode($q->options_json, true) ?: array();
                foreach ($options as $key => $val) {
                    $answer = new \stdClass();
                    $answer->question = $qid;
                    $answer->answer = $val;
                    $answer->answerformat = FORMAT_HTML;
                    // Fraction score (1.0 for correct, 0.0 for wrong)
                    $answer->fraction = ($key === $q->correct_answer) ? 1.0 : 0.0;
                    $answer->feedback = ($key === $q->correct_answer) ? 'Correct choice' : 'Wrong choice';
                    $answer->feedbackformat = FORMAT_HTML;
                    $DB->insert_record('question_answers', $answer);
                }

            } else if ($q->qtype === 'truefalse') {
                $question->qtype = 'truefalse';
                $qid = $DB->insert_record('question', $question);

                // Options
                $tf = new \stdClass();
                $tf->questionid = $qid;
                $DB->insert_record('qtype_truefalse_options', $tf);

                // Insert True and False answers
                // Moodle conventions: True answer is answer record 1, False is 2
                $answers = array(
                    'True' => ($q->correct_answer === 'true' || $q->correct_answer === '1') ? 1.0 : 0.0,
                    'False' => ($q->correct_answer === 'false' || $q->correct_answer === '0') ? 1.0 : 0.0
                );
                foreach ($answers as $ans => $frac) {
                    $answer = new \stdClass();
                    $answer->question = $qid;
                    $answer->answer = $ans;
                    $answer->answerformat = FORMAT_HTML;
                    $answer->fraction = $frac;
                    $answer->feedback = '';
                    $answer->feedbackformat = FORMAT_HTML;
                    $DB->insert_record('question_answers', $answer);
                }

            } else if ($q->qtype === 'essay') {
                $question->qtype = 'essay';
                $qid = $DB->insert_record('question', $question);

                // Options
                $essay = new \stdClass();
                $essay->questionid = $qid;
                $essay->responseformat = 'editor';
                $essay->responserequired = 1;
                $essay->responsefieldlines = 15;
                $essay->graderinfo = $q->reviewer_comment; // expected guidelines stored here
                $essay->graderinfoformat = FORMAT_HTML;
                $DB->insert_record('qtype_essay_options', $essay);
            }

            // Link local question ID to moodle question ID
            $q->moodle_questionid = $qid;
            $q->timemodified = time();
            $DB->update_record('local_aiacademic_questions', $q);

            $moodlequestionids[] = $qid;
            $publishedcount++;
        }

        // Add questions to quiz if target is a quiz resource
        if ($target_type === 'quiz' && $target_quiz_id > 0 && !empty($moodlequestionids)) {
            // Moodle quiz structure import logic (can use quiz_add_quiz_question)
            foreach ($moodlequestionids as $qid) {
                // Insert page placement
                // Note: Direct database insertion is simple for PoC; production should use quiz core API
                $qq = new \stdClass();
                $qq->quizid = $target_quiz_id;
                $qq->questionid = $qid;
                $qq->page = 1;
                $qq->slot = $DB->count_records('quiz_slots', array('quizid' => $target_quiz_id)) + 1;
                $DB->insert_record('quiz_slots', $qq);
            }
            // Update quiz sumgrades
            $quiz = $DB->get_record('quiz', array('id' => $target_quiz_id));
            if ($quiz) {
                $quiz->sumgrades = $DB->count_records('quiz_slots', array('quizid' => $target_quiz_id));
                $DB->update_record('quiz', $quiz);
            }
        }

        // Update batch status to published
        $batch->status = 3; // published
        $DB->update_record('local_aiacademic_genquizzes', $batch);

        return array(
            'published' => true,
            'questions_published' => $publishedcount,
            'moodle_question_ids' => $moodlequestionids
        );
    }

    /**
     * Return description for publish.
     */
    public static function publish_returns() {
        return new external_single_structure(array(
            'published' => new external_value(PARAM_BOOL, 'Publish status'),
            'questions_published' => new external_value(PARAM_INT, 'Number of questions published'),
            'moodle_question_ids' => new external_multiple_structure(new external_value(PARAM_INT, 'Moodle question ID'))
        ));
    }

    /**
     * Parameter description for get_genquiz.
     */
    public static function get_genquiz_parameters() {
        return new external_function_parameters(array(
            'genquiz_id' => new external_value(PARAM_INT, 'Batch Generated Quiz ID', VALUE_REQUIRED)
        ));
    }

    /**
     * Get generated quiz draft details and its questions.
     */
    public static function get_genquiz($genquiz_id) {
        global $DB;

        $params = self::validate_parameters(self::get_genquiz_parameters(), array(
            'genquiz_id' => $genquiz_id
        ));

        $batch = $DB->get_record('local_aiacademic_genquizzes', array('id' => $params['genquiz_id']), '*', MUST_EXIST);
        $coursecontext = \context_course::instance($batch->courseid);
        self::validate_context($coursecontext);
        require_capability('local/aiacademic:generatequiz', $coursecontext);

        $questions = $DB->get_records('local_aiacademic_questions', array('genquizid' => $batch->id));
        $qlist = array();
        foreach ($questions as $q) {
            $qlist[] = array(
                'id' => (int)$q->id,
                'type' => $q->qtype,
                'question' => $q->question_text,
                'options' => $q->options_json ? json_decode($q->options_json, true) : array(),
                'correct_answer' => $q->correct_answer,
                'explanation' => $q->explanation,
                'difficulty' => $q->difficulty,
                'review_status' => (int)$q->review_status
            );
        }

        return array(
            'genquiz_id' => (int)$batch->id,
            'questions' => $qlist
        );
    }

    /**
     * Return description for get_genquiz.
     */
    public static function get_genquiz_returns() {
        return self::generate_returns();
    }

    /**
     * Parameter description for list_genquizzes.
     */
    public static function list_genquizzes_parameters() {
        return new external_function_parameters(array(
            'courseid' => new external_value(PARAM_INT, 'Course ID context', VALUE_REQUIRED),
            'page'     => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
            'perpage'  => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 10)
        ));
    }

    /**
     * List generated quiz drafts in course.
     */
    public static function list_genquizzes($courseid, $page = 1, $perpage = 10) {
        global $DB;

        $params = self::validate_parameters(self::list_genquizzes_parameters(), array(
            'courseid' => $courseid,
            'page' => $page,
            'perpage' => $perpage
        ));

        $coursecontext = \context_course::instance($params['courseid']);
        self::validate_context($coursecontext);
        require_capability('local/aiacademic:generatequiz', $coursecontext);

        $courseid = $params['courseid'];
        $page = max(1, $params['page']);
        $perpage = max(1, min(50, $params['perpage']));
        $offset = ($page - 1) * $perpage;

        $quizzes = $DB->get_records(
            'local_aiacademic_genquizzes',
            array('courseid' => $courseid),
            'timecreated DESC',
            'id, source_filename, difficulty, requested_count, status, timecreated',
            $offset,
            $perpage
        );

        $result = array();
        foreach ($quizzes as $q) {
            $result[] = array(
                'id' => (int)$q->id,
                'source_filename' => $q->source_filename,
                'difficulty' => $q->difficulty,
                'requested_count' => (int)$q->requested_count,
                'status' => (int)$q->status,
                'timecreated' => (int)$q->timecreated
            );
        }

        return $result;
    }

    /**
     * Return description for list_genquizzes.
     */
    public static function list_genquizzes_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id' => new external_value(PARAM_INT, 'Batch ID'),
                'source_filename' => new external_value(PARAM_TEXT, 'Filename'),
                'difficulty' => new external_value(PARAM_TEXT, 'Difficulty'),
                'requested_count' => new external_value(PARAM_INT, 'Requested questions'),
                'status' => new external_value(PARAM_INT, 'Batch status code'),
                'timecreated' => new external_value(PARAM_INT, 'Created time')
            ))
        );
    }
}
