<?php
// This file is part of courseimport block in Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade script.
 *
 * @package   block_courseimport
 * @copyright 2018, University of Nottingham
 * @author    Neill Magill <neill.magill@nottingham.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade script for the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_courseimport_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2018050900) {
        unset_config('filesize', 'block_courseimport');
        upgrade_block_savepoint(true, 2018050900, 'courseimport');
    }

    if ($oldversion < 2020012200) {
        // Rename database columns to something sane.
        $table = new xmldb_table('block_courseimport');
        $sourcefield = new xmldb_field('targetcourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $targetfield = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        if ($dbman->field_exists($table, $sourcefield)) {
            $dbman->rename_field($table, $sourcefield, 'source');
        }
        if ($dbman->field_exists($table, $targetfield)) {
            $dbman->rename_field($table, $targetfield, 'target');
        }
        upgrade_block_savepoint(true, 2020012200, 'courseimport');
    }

    if ($oldversion < 2020012300) {
        unset_config('crontime', 'block_courseimport');
        upgrade_block_savepoint(true, 2020012300, 'courseimport');
    }

    if ($oldversion < 2020012301) {
        // Add the database fields to store backuup and restore progress.
        $table = new xmldb_table('block_courseimport');
        $backupfield = new xmldb_field('backupprogress', XMLDB_TYPE_NUMBER, '15,14', null, XMLDB_NOTNULL, null, '0');
        $restorefield = new xmldb_field('restoreprogress', XMLDB_TYPE_NUMBER, '15,14', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $backupfield)) {
            $dbman->add_field($table, $backupfield);
        }
        if (!$dbman->field_exists($table, $restorefield)) {
            $dbman->add_field($table, $restorefield);
        }
        upgrade_block_savepoint(true, 2020012301, 'courseimport');
    }

    if ($oldversion < 2026041700) {
        $table = new xmldb_table('block_courseimport');
        $field = new xmldb_field('bulk_job_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $bulktable = new xmldb_table('block_courseimport_bulk_job');
        if (!$dbman->table_exists($bulktable)) {
            $bulktable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $bulktable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $bulktable->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'queued');
            $bulktable->add_field('total_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $bulktable->add_field('completed_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $bulktable->add_field('failed_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $bulktable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $bulktable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $bulktable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($bulktable);
        }

        upgrade_block_savepoint(true, 2026041700, 'courseimport');
    }

    if ($oldversion < 2026041800) {
        // These settings were removed — clean up any existing values.
        unset_config('bulkmaxrows', 'block_courseimport');
        unset_config('bulkcsvmaxbytes', 'block_courseimport');
        unset_config('bulknewcoursecategory', 'block_courseimport');
        upgrade_block_savepoint(true, 2026041800, 'courseimport');
    }

    return true;
}
