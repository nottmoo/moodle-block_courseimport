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

    return true;
}
