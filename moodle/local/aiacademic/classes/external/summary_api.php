<?php
// ============================================================================
// Moodle local_aiacademic — External API for Summary
// Handles material summarization
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

class summary_api extends external_api {

    /**
     * Parameter description for generate.
     */
    public static function generate_parameters() {
        return new external_function_parameters(array(
            'courseid'         => new external_value(PARAM_INT, 'Course ID context', VALUE_REQUIRED),
            'cmid'             => new external_value(PARAM_INT, 'Course Module ID of the file resource', VALUE_REQUIRED),
            'force_regenerate' => new external_value(PARAM_BOOL, 'Re-generate even if summary exists', VALUE_DEFAULT, false)
        ));
    }

    /**
     * Generate summary for a course material.
     */
    public static function generate($courseid, $cmid, $force_regenerate = false) {
        global $DB, $USER;

        $params = self::validate_parameters(self::generate_parameters(), array(
            'courseid' => $courseid,
            'cmid' => $cmid,
            'force_regenerate' => $force_regenerate
        ));

        $coursecontext = \context_course::instance($params['courseid']);
        self::validate_context($coursecontext);
        require_capability('local/aiacademic:summarize', $coursecontext);

        $courseid = $params['courseid'];
        $cmid = $params['cmid'];
        $forceregenerate = $params['force_regenerate'];

        // 1. Verify Course Module exists and is a file resource
        $cm = get_coursemodule_from_id('resource', $cmid, $courseid, false, MUST_EXIST);
        $resource = $DB->get_record('resource', array('id' => $cm->instance), '*', MUST_EXIST);

        // 2. Fetch the file associated with this resource
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_resource',
            'content',
            0,
            'sortorder DESC, id ASC',
            false
        );

        if (empty($files)) {
            throw new moodle_exception('filenotfound', 'error');
        }

        /** @var \stored_file $file */
        $file = reset($files);
        $filename = $file->get_filename();
        $contenthash = $file->get_contenthash();
        $contenttype = $file->get_mimetype();
        $filebytes = $file->get_content();

        if (!$forceregenerate) {
            $existingrecords = $DB->get_records('local_aiacademic_summaries', array(
                'source_hash' => $contenthash,
                'status' => 1
            ), 'id DESC', '*', 0, 1);
            $existing = !empty($existingrecords) ? reset($existingrecords) : null;
            if ($existing) {
                return array(
                    'summary_id' => (int)$existing->id,
                    'executive_summary' => $existing->executive_summary,
                    'key_points' => json_decode($existing->key_points_json, true) ?: array(),
                    'concepts' => json_decode($existing->concepts_json, true) ?: array(),
                    'glossary' => json_decode($existing->glossary_json, true) ?: array(),
                    'study_guide' => $existing->study_guide,
                    'model' => $existing->model_used,
                    'generation_time' => $existing->generation_time
                );
            }
        }

        // 4. Create record in pending state
        $summary = new \stdClass();
        $summary->userid = $USER->id;
        $summary->courseid = $courseid;
        $summary->cmid = $cmid;
        $summary->source_filename = $filename;
        $summary->source_hash = $contenthash;
        $summary->status = 0; // pending
        $summary->timecreated = time();
        $summary->timemodified = time();
        $summaryid = $DB->insert_record('local_aiacademic_summaries', $summary);

        // 5. Call AI Service client
        $client = new ai_client();
        $status = 'success';
        $errormsg = null;
        $result = null;

        try {
            $base64 = base64_encode($filebytes);
            $result = $client->generate_summary($USER->id, $courseid, $filename, $contenttype, $base64);
        } catch (\Exception $e) {
            $status = 'error';
            $errormsg = $e->getMessage();

            $summary->id = $summaryid;
            $summary->status = 2; // failed
            $summary->timemodified = time();
            $DB->update_record('local_aiacademic_summaries', $summary);
            throw $e;
        }

        // 6. Update summary record with results
        $summary->id = $summaryid;
        $summary->executive_summary = $result['executive_summary'];
        $summary->key_points_json = json_encode($result['key_points']);
        
        $concepts = array();
        foreach ($result['important_concepts'] as $c) {
            $concepts[] = array('term' => $c['term'], 'definition' => $c['definition']);
        }
        $summary->concepts_json = json_encode($concepts);

        $glossary = array();
        foreach ($result['glossary'] as $g) {
            $glossary[] = array('term' => $g['term'], 'definition' => $g['definition']);
        }
        $summary->glossary_json = json_encode($glossary);

        $summary->study_guide = $result['study_guide'];
        $summary->model_used = $result['metadata']['model'];
        $summary->tokens_used = $result['metadata']['tokens']['total'];
        $summary->generation_time = $result['metadata']['generation_time_seconds'];
        $summary->status = 1; // completed
        $summary->timemodified = time();
        $DB->update_record('local_aiacademic_summaries', $summary);

        // 7. Log operation
        $log = new \stdClass();
        $log->userid = $USER->id;
        $log->feature_type = 'summary';
        $log->model_used = $summary->model_used;
        $log->input_tokens = $result['metadata']['tokens']['input'];
        $log->output_tokens = $result['metadata']['tokens']['output'];
        $log->response_time_ms = $summary->generation_time * 1000;
        $log->status = $status;
        $log->error_message = $errormsg;
        $log->ip_address = getremoteaddr();
        $log->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
        $log->timecreated = time();
        $DB->insert_record('local_aiacademic_logs', $log);

        return array(
            'summary_id' => $summaryid,
            'executive_summary' => $summary->executive_summary,
            'key_points' => $result['key_points'],
            'concepts' => $concepts,
            'glossary' => $glossary,
            'study_guide' => $summary->study_guide,
            'model' => $summary->model_used,
            'generation_time' => $summary->generation_time
        );
    }

    /**
     * Return description for generate.
     */
    public static function generate_returns() {
        return new external_single_structure(array(
            'summary_id'        => new external_value(PARAM_INT, 'Summary ID'),
            'executive_summary' => new external_value(PARAM_RAW, 'Executive summary content'),
            'key_points'        => new external_multiple_structure(new external_value(PARAM_RAW, 'Key point')),
            'concepts'          => new external_multiple_structure(
                new external_single_structure(array(
                    'term' => new external_value(PARAM_TEXT, 'Concept name'),
                    'definition' => new external_value(PARAM_RAW, 'Concept definition')
                ))
            ),
            'glossary'          => new external_multiple_structure(
                new external_single_structure(array(
                    'term' => new external_value(PARAM_TEXT, 'Term name'),
                    'definition' => new external_value(PARAM_RAW, 'Term definition')
                ))
            ),
            'study_guide'       => new external_value(PARAM_RAW, 'Study guide content'),
            'model'             => new external_value(PARAM_TEXT, 'Model used'),
            'generation_time'   => new external_value(PARAM_FLOAT, 'Generation time in seconds')
        ));
    }

    /**
     * Parameter description for get_summary.
     */
    public static function get_summary_parameters() {
        return new external_function_parameters(array(
            'summaryid' => new external_value(PARAM_INT, 'Summary ID', VALUE_REQUIRED)
        ));
    }

    /**
     * Get summary detail by ID.
     */
    public static function get_summary($summaryid) {
        global $DB;

        $params = self::validate_parameters(self::get_summary_parameters(), array(
            'summaryid' => $summaryid
        ));

        $summaryid = $params['summaryid'];
        $summary = $DB->get_record('local_aiacademic_summaries', array('id' => $summaryid), '*', MUST_EXIST);

        $coursecontext = \context_course::instance($summary->courseid);
        self::validate_context($coursecontext);
        require_capability('local/aiacademic:summarize', $coursecontext);

        return array(
            'summary_id' => (int)$summary->id,
            'executive_summary' => $summary->executive_summary,
            'key_points' => json_decode($summary->key_points_json, true) ?: array(),
            'concepts' => json_decode($summary->concepts_json, true) ?: array(),
            'glossary' => json_decode($summary->glossary_json, true) ?: array(),
            'study_guide' => $summary->study_guide,
            'model' => $summary->model_used,
            'generation_time' => $summary->generation_time
        );
    }

    /**
     * Return description for get_summary.
     */
    public static function get_summary_returns() {
        return self::generate_returns();
    }

    /**
     * Parameter description for list_summaries.
     */
    public static function list_summaries_parameters() {
        return new external_function_parameters(array(
            'courseid' => new external_value(PARAM_INT, 'Course ID context', VALUE_REQUIRED),
            'page'     => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
            'perpage'  => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 10)
        ));
    }

    /**
     * List summaries in a course.
     */
    public static function list_summaries($courseid, $page = 1, $perpage = 10) {
        global $DB;

        $params = self::validate_parameters(self::list_summaries_parameters(), array(
            'courseid' => $courseid,
            'page' => $page,
            'perpage' => $perpage
        ));

        $coursecontext = \context_course::instance($params['courseid']);
        self::validate_context($coursecontext);
        require_capability('local/aiacademic:summarize', $coursecontext);

        $courseid = $params['courseid'];
        $page = max(1, $params['page']);
        $perpage = max(1, min(50, $params['perpage']));
        $offset = ($page - 1) * $perpage;

        $summaries = $DB->get_records(
            'local_aiacademic_summaries',
            array('courseid' => $courseid, 'status' => 1),
            'timecreated DESC',
            'id, source_filename, cmid, timecreated',
            $offset,
            $perpage
        );

        $result = array();
        foreach ($summaries as $s) {
            $result[] = array(
                'id' => (int)$s->id,
                'source_filename' => $s->source_filename,
                'cmid' => $s->cmid ? (int)$s->cmid : 0,
                'timecreated' => (int)$s->timecreated
            );
        }

        return $result;
    }

    /**
     * Return description for list_summaries.
     */
    public static function list_summaries_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'id' => new external_value(PARAM_INT, 'Summary ID'),
                'source_filename' => new external_value(PARAM_TEXT, 'Filename'),
                'cmid' => new external_value(PARAM_INT, 'Course Module ID'),
                'timecreated' => new external_value(PARAM_INT, 'Created time')
            ))
        );
    }
}
