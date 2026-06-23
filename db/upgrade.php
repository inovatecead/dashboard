<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_dashboard
 */
function xmldb_local_dashboard_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061200) {
        // Add turma field to notices table.
        $table = new xmldb_table('local_dashboard_notices');
        $field = new xmldb_field('turma', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'date_end');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026061200, 'local', 'dashboard');
    }

    if ($oldversion < 2026033101) {
        // Create local_dashboard_notices table.
        $table = new xmldb_table('local_dashboard_notices');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'info');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('date_start', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('date_end', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('enabled_sortorder', XMLDB_INDEX_NOTUNIQUE, ['enabled', 'sortorder']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026033101, 'local', 'dashboard');
    }

    if ($oldversion < 2026062300) {
        // Create local_dashboard_banners table.
        $table = new xmldb_table('local_dashboard_banners');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('alt_text', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('link_url', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('enabled_sortorder', XMLDB_INDEX_NOTUNIQUE, ['enabled', 'sortorder']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026062300, 'local', 'dashboard');
    }

    if ($oldversion < 2026062301) {
        // Add turma field to banners table.
        $table = new xmldb_table('local_dashboard_banners');
        $field = new xmldb_field('turma', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026062301, 'local', 'dashboard');
    }

    return true;
}
