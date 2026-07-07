<?php
// ============================================================================
// Moodle local_aiacademic - Database upgrade steps.
// ============================================================================

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade local_aiacademic database schema.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_local_aiacademic_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026070700) {
        $table = new xmldb_table('local_aiacademic_genquizzes');

        $field = new xmldb_field('moodle_quizid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'cmid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('moodle_cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'moodle_quizid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('idx_gq_moodlequiz', XMLDB_INDEX_NOTUNIQUE, array('moodle_quizid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026070700, 'local', 'aiacademic');
    }

    if ($oldversion < 2026070701) {
        $currenttimeout = (int)get_config('local_aiacademic', 'connection_timeout');
        if ($currenttimeout <= 30) {
            set_config('connection_timeout', 300, 'local_aiacademic');
        }

        upgrade_plugin_savepoint(true, 2026070701, 'local', 'aiacademic');
    }

    return true;
}
