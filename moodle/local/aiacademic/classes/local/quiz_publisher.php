<?php
// ============================================================================
// Moodle local_aiacademic - Quiz publishing helper.
// Creates Moodle question bank questions and quiz activities from AI drafts.
// ============================================================================

namespace local_aiacademic\local;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;
use question_bank;

class quiz_publisher {

    /**
     * Create Moodle questions in the course question bank from reviewed AI draft rows.
     *
     * @param \stdClass $batch Generation batch.
     * @param array $questions Approved local_aiacademic_questions records.
     * @param string $categoryname Optional category name.
     * @return int[] Moodle question IDs.
     */
    public static function publish_questions(\stdClass $batch, array $questions, string $categoryname = ''): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');

        $coursecontext = \context_course::instance($batch->courseid);
        $category = self::get_or_create_category($coursecontext, $batch, $categoryname);
        $questionids = array();

        foreach ($questions as $questionrecord) {
            if (!empty($questionrecord->moodle_questionid)) {
                $questionids[] = (int)$questionrecord->moodle_questionid;
                continue;
            }

            $question = self::create_moodle_question($questionrecord, $category, $coursecontext);

            $questionrecord->moodle_questionid = $question->id;
            $questionrecord->timemodified = time();
            $DB->update_record('local_aiacademic_questions', $questionrecord);

            $questionids[] = (int)$question->id;
        }

        return $questionids;
    }

    /**
     * Create a quiz activity in the same section as the source module and add questions.
     *
     * @param \stdClass $batch Generation batch.
     * @param int[] $questionids Moodle question IDs.
     * @param string $quizname Optional quiz name.
     * @param array $settings Quiz activity settings.
     * @return array Created quiz metadata.
     */
    public static function create_quiz_in_source_section(\stdClass $batch, array $questionids, string $quizname = '',
            array $settings = array()): array {
        global $CFG, $DB;

        if (empty($questionids)) {
            throw new moodle_exception('validation_error', 'local_aiacademic', '', null, 'No questions available for quiz creation.');
        }

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $course = $DB->get_record('course', array('id' => $batch->courseid), '*', MUST_EXIST);

        if (!empty($batch->moodle_quizid) && !empty($batch->moodle_cmid)) {
            $quiz = $DB->get_record('quiz', array('id' => $batch->moodle_quizid), '*', MUST_EXIST);
            $cm = get_coursemodule_from_id('quiz', $batch->moodle_cmid, $course->id, false, MUST_EXIST);
        } else {
            $sectionnum = self::get_source_section_number($batch);
            $quizname = trim($quizname) ?: self::setting_value($settings, 'quiz_name', '');
            $quizname = trim($quizname) ?: self::build_quiz_name($batch);
            $moduleinfo = self::build_quiz_module_info($course, $sectionnum, $quizname, count($questionids), $settings);
            $moduleinfo = add_moduleinfo($moduleinfo, $course, null);

            $cm = get_coursemodule_from_id('quiz', $moduleinfo->coursemodule, $course->id, false, MUST_EXIST);
            $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);

            $batch->moodle_quizid = $quiz->id;
            $batch->moodle_cmid = $cm->id;
        }

        foreach ($questionids as $questionid) {
            quiz_add_quiz_question((int)$questionid, $quiz, 0, 1);
        }

        self::apply_quiz_settings($quiz->id, $cm->id, $settings, count($questionids));
        self::sync_quiz_grades($quiz->id);
        rebuild_course_cache($course->id, true);

        return array(
            'quizid' => (int)$quiz->id,
            'cmid' => (int)$cm->id,
            'url' => (new \moodle_url('/mod/quiz/view.php', array('id' => $cm->id)))->out(false),
        );
    }

    /**
     * Add generated questions to an existing quiz activity.
     *
     * @param int $quizid Quiz instance ID.
     * @param int[] $questionids Moodle question IDs.
     * @param array $settings Quiz activity settings.
     * @return array Quiz metadata.
     */
    public static function add_questions_to_existing_quiz(int $quizid, array $questionids, array $settings = array()): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quiz = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);

        foreach ($questionids as $questionid) {
            quiz_add_quiz_question((int)$questionid, $quiz, 0, 1);
        }

        self::apply_quiz_settings($quiz->id, $cm->id, $settings, count($questionids));
        self::sync_quiz_grades($quiz->id);
        rebuild_course_cache($quiz->course, true);

        return array(
            'quizid' => (int)$quiz->id,
            'cmid' => (int)$cm->id,
            'url' => (new \moodle_url('/mod/quiz/view.php', array('id' => $cm->id)))->out(false),
        );
    }

    /**
     * Get or create a category for AI generated questions.
     *
     * @param \context_course $context Course context.
     * @param \stdClass $batch Generation batch.
     * @param string $categoryname Optional category name.
     * @return \stdClass
     */
    private static function get_or_create_category(\context_course $context, \stdClass $batch, string $categoryname): \stdClass {
        global $DB;

        $defaultcategory = question_make_default_categories(array($context));
        $categoryname = trim($categoryname) ?: 'AI Generated - ' . $batch->source_filename;

        $category = $DB->get_record('question_categories', array(
            'contextid' => $context->id,
            'parent' => $defaultcategory->id,
            'name' => $categoryname,
        ));

        if ($category) {
            return $category;
        }

        $category = new \stdClass();
        $category->name = $categoryname;
        $category->contextid = $context->id;
        $category->info = 'AI-generated questions from file ' . $batch->source_filename;
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = $defaultcategory->id;
        $category->sortorder = 999;
        $category->id = $DB->insert_record('question_categories', $category);

        return $category;
    }

    /**
     * Create a Moodle question using qtype save APIs so Moodle 4 question bank tables remain valid.
     *
     * @param \stdClass $record Local generated question record.
     * @param \stdClass $category Question category.
     * @param \context_course $context Course context.
     * @return \stdClass Saved Moodle question.
     */
    private static function create_moodle_question(\stdClass $record, \stdClass $category, \context_course $context): \stdClass {
        $form = self::build_common_question_form($record, $category, $context);

        if ($record->qtype === 'multichoice') {
            self::apply_multichoice_fields($form, $record);
        } else if ($record->qtype === 'truefalse') {
            self::apply_truefalse_fields($form, $record);
        } else if ($record->qtype === 'essay') {
            self::apply_essay_fields($form, $record);
        } else {
            throw new moodle_exception('validation_error', 'local_aiacademic', '', null, 'Unsupported question type: ' . $record->qtype);
        }

        $question = new \stdClass();
        $question->qtype = $record->qtype;

        return question_bank::get_qtype($record->qtype)->save_question($question, $form);
    }

    /**
     * Build common question form object accepted by qtype save_question().
     *
     * @param \stdClass $record Local question record.
     * @param \stdClass $category Question category.
     * @param \context_course $context Course context.
     * @return \stdClass
     */
    private static function build_common_question_form(\stdClass $record, \stdClass $category, \context_course $context): \stdClass {
        $form = new \stdClass();
        $form->category = $category->id . ',' . $context->id;
        $form->name = shorten_text(strip_tags($record->question_text), 60);
        $form->questiontext = array('text' => $record->question_text, 'format' => FORMAT_HTML);
        $form->generalfeedback = array('text' => $record->explanation ?: '', 'format' => FORMAT_HTML);
        $form->defaultmark = 1;
        $form->penalty = 0.3333333;
        $form->idnumber = '';
        $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

        return $form;
    }

    /**
     * Apply multichoice-specific fields.
     *
     * @param \stdClass $form Form object.
     * @param \stdClass $record Local question record.
     */
    private static function apply_multichoice_fields(\stdClass $form, \stdClass $record): void {
        $options = json_decode($record->options_json ?: '[]', true);
        if (!is_array($options) || count($options) < 2) {
            throw new moodle_exception('validation_error', 'local_aiacademic', '', null, 'Multiple choice questions require at least two options.');
        }

        $correctkey = self::resolve_correct_option_key($options, $record->correct_answer);
        $form->single = 1;
        $form->shuffleanswers = 1;
        $form->answernumbering = 'abc';
        $form->showstandardinstruction = 0;
        $form->answer = array();
        $form->fraction = array();
        $form->feedback = array();

        foreach ($options as $key => $value) {
            $form->answer[] = array('text' => (string)$value, 'format' => FORMAT_HTML);
            $form->fraction[] = ((string)$key === (string)$correctkey) ? 1.0 : 0.0;
            $form->feedback[] = array(
                'text' => ((string)$key === (string)$correctkey) ? 'Correct choice.' : '',
                'format' => FORMAT_HTML,
            );
        }

        $form->correctfeedback = array('text' => 'Correct answer.', 'format' => FORMAT_HTML);
        $form->partiallycorrectfeedback = array('text' => 'Partially correct.', 'format' => FORMAT_HTML);
        $form->incorrectfeedback = array('text' => 'Incorrect answer.', 'format' => FORMAT_HTML);
        $form->shownumcorrect = 0;
    }

    /**
     * Apply true/false-specific fields.
     *
     * @param \stdClass $form Form object.
     * @param \stdClass $record Local question record.
     */
    private static function apply_truefalse_fields(\stdClass $form, \stdClass $record): void {
        $answer = strtolower(trim((string)$record->correct_answer));
        $form->correctanswer = in_array($answer, array('true', '1', 'yes'), true) ? 1 : 0;
        $form->feedbacktrue = array('text' => $form->correctanswer ? 'Correct.' : '', 'format' => FORMAT_HTML);
        $form->feedbackfalse = array('text' => !$form->correctanswer ? 'Correct.' : '', 'format' => FORMAT_HTML);
        $form->showstandardinstruction = 0;
    }

    /**
     * Apply essay-specific fields.
     *
     * @param \stdClass $form Form object.
     * @param \stdClass $record Local question record.
     */
    private static function apply_essay_fields(\stdClass $form, \stdClass $record): void {
        $form->responseformat = 'editor';
        $form->responserequired = 1;
        $form->responsefieldlines = 15;
        $form->attachments = 0;
        $form->attachmentsrequired = 0;
        $form->maxbytes = 0;
        $form->graderinfo = array('text' => $record->reviewer_comment ?: $record->correct_answer ?: '', 'format' => FORMAT_HTML);
        $form->responsetemplate = array('text' => '', 'format' => FORMAT_HTML);
    }

    /**
     * Resolve the AI correct answer key against the generated options.
     *
     * @param array $options Answer options.
     * @param string|null $correctanswer Correct answer from AI.
     * @return string|int
     */
    private static function resolve_correct_option_key(array $options, ?string $correctanswer) {
        $correctanswer = trim((string)$correctanswer);

        if (array_key_exists($correctanswer, $options)) {
            return $correctanswer;
        }

        foreach ($options as $key => $value) {
            if (trim((string)$value) === $correctanswer) {
                return $key;
            }
        }

        return array_key_first($options);
    }

    /**
     * Determine the course section number from the source resource module.
     *
     * @param \stdClass $batch Generation batch.
     * @return int
     */
    private static function get_source_section_number(\stdClass $batch): int {
        global $DB;

        if (empty($batch->cmid)) {
            return 0;
        }

        $cm = get_coursemodule_from_id('resource', $batch->cmid, $batch->courseid, false, MUST_EXIST);
        $section = $DB->get_record('course_sections', array('id' => $cm->section), 'section', MUST_EXIST);

        return (int)$section->section;
    }

    /**
     * Build a readable quiz name from the generation source.
     *
     * @param \stdClass $batch Generation batch.
     * @return string
     */
    private static function build_quiz_name(\stdClass $batch): string {
        $source = pathinfo($batch->source_filename, PATHINFO_FILENAME);
        $source = trim($source) ?: 'AI Generated Quiz';

        return shorten_text('AI Quiz - ' . $source, 255);
    }

    /**
     * Build the module info object required by add_moduleinfo().
     *
     * @param \stdClass $course Course record.
     * @param int $sectionnum Course section number.
     * @param string $quizname Quiz name.
     * @param int $questioncount Number of questions.
     * @return \stdClass
     */
    private static function build_quiz_module_info(\stdClass $course, int $sectionnum, string $quizname, int $questioncount,
            array $settings = array()): \stdClass {
        list($module, $context, $courseformatsection, $cm, $moduleinfo) =
            prepare_new_moduleinfo_data($course, 'quiz', $sectionnum);
        $moduleinfo->name = $quizname;
        $moduleinfo->intro = 'Generated from course material using AI Quiz Generator.';
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->showdescription = 0;
        $moduleinfo->visible = self::setting_value($settings, 'visible', 1) ? 1 : 0;
        $moduleinfo->visibleoncoursepage = $moduleinfo->visible;

        $moduleinfo->timeopen = self::setting_value($settings, 'timeopen', 0);
        $moduleinfo->timeclose = self::setting_value($settings, 'timeclose', 0);
        $moduleinfo->timelimit = self::setting_value($settings, 'timelimit', 0);
        $moduleinfo->overduehandling = 'autosubmit';
        $moduleinfo->graceperiod = 0;
        $moduleinfo->grade = self::setting_value($settings, 'grade', max(1, $questioncount));
        $moduleinfo->sumgrades = 0;
        $moduleinfo->attempts = self::setting_value($settings, 'attempts', 1);
        $moduleinfo->grademethod = QUIZ_GRADEHIGHEST;
        $moduleinfo->questionsperpage = self::setting_value($settings, 'questionsperpage', 1);
        $moduleinfo->navmethod = QUIZ_NAVMETHOD_FREE;
        $moduleinfo->shuffleanswers = self::setting_value($settings, 'shuffleanswers', 1) ? 1 : 0;
        $moduleinfo->preferredbehaviour = 'deferredfeedback';
        $moduleinfo->canredoquestions = 0;
        $moduleinfo->decimalpoints = 2;
        $moduleinfo->questiondecimalpoints = -1;
        $moduleinfo->quizpassword = '';
        $moduleinfo->subnet = '';
        $moduleinfo->browsersecurity = '-';
        $moduleinfo->delay1 = 0;
        $moduleinfo->delay2 = 0;
        $moduleinfo->showuserpicture = 0;
        $moduleinfo->showblocks = 0;

        foreach (array('attempt', 'correctness', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback') as $field) {
            $moduleinfo->{$field . 'during'} = 0;
            $moduleinfo->{$field . 'immediately'} = 1;
            $moduleinfo->{$field . 'open'} = 1;
            $moduleinfo->{$field . 'closed'} = 1;
        }

        $moduleinfo->completion = 0;
        $moduleinfo->completionpassgrade = 0;
        $moduleinfo->completionview = 0;
        $moduleinfo->completionexpected = 0;
        $moduleinfo->completionunlocked = 1;

        return $moduleinfo;
    }

    /**
     * Apply mutable quiz settings after module creation or when reusing an existing generated quiz.
     *
     * @param int $quizid Quiz instance ID.
     * @param int $cmid Course module ID.
     * @param array $settings Quiz activity settings.
     * @param int $questioncount Number of questions being published.
     */
    private static function apply_quiz_settings(int $quizid, int $cmid, array $settings, int $questioncount): void {
        global $DB;

        if (empty($settings)) {
            return;
        }

        $quiz = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);
        $quiz->timeopen = self::setting_value($settings, 'timeopen', $quiz->timeopen);
        $quiz->timeclose = self::setting_value($settings, 'timeclose', $quiz->timeclose);
        $quiz->timelimit = self::setting_value($settings, 'timelimit', $quiz->timelimit);
        $quiz->attempts = self::setting_value($settings, 'attempts', $quiz->attempts);
        $quiz->grade = self::setting_value($settings, 'grade', max(1, $questioncount));
        $quiz->questionsperpage = self::setting_value($settings, 'questionsperpage', $quiz->questionsperpage);
        $quiz->shuffleanswers = self::setting_value($settings, 'shuffleanswers', $quiz->shuffleanswers) ? 1 : 0;
        $DB->update_record('quiz', $quiz);

        $cm = $DB->get_record('course_modules', array('id' => $cmid), '*', MUST_EXIST);
        $cm->visible = self::setting_value($settings, 'visible', $cm->visible) ? 1 : 0;
        $cm->visibleold = $cm->visible;
        $DB->update_record('course_modules', $cm);
    }

    /**
     * Read a setting value with a default.
     *
     * @param array $settings Settings map.
     * @param string $key Setting name.
     * @param mixed $default Default value.
     * @return mixed
     */
    private static function setting_value(array $settings, string $key, $default) {
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Sync quiz sumgrades and maximum grade after adding slots.
     *
     * @param int $quizid Quiz instance ID.
     */
    private static function sync_quiz_grades(int $quizid): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $sumgrades = (float)$DB->get_field_sql(
            'SELECT COALESCE(SUM(maxmark), 0) FROM {quiz_slots} WHERE quizid = ?',
            array($quizid)
        );

        $quiz = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);
        $quiz->sumgrades = $sumgrades;
        $quiz->grade = max((float)$quiz->grade, $sumgrades);
        $DB->update_record('quiz', $quiz);

        quiz_update_grades($quiz);
    }
}
