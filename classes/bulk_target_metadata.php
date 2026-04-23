<?php
// This file is part of the courseimport block plugin for Moodle
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

namespace block_courseimport;

defined('MOODLE_INTERNAL') || die();

/**
 * Apply CSV-driven target metadata before backup/restore is queued.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class bulk_target_metadata {
    /**
     * fullname + idnumber from CSV; start/end dates from source with year taken from CSV/target shortname.
     *
     * @param int $targetid
     * @param int $sourceid
     * @param array<string, mixed> $pair Must include csv_fullname, csv_shortname, csv_idnumber keys when present.
     */
    public static function sync_before_queue(int $targetid, int $sourceid, array $pair): void {
        global $DB;
        $fn = trim((string) ($pair['csv_fullname'] ?? ''));
        if ($fn !== '') {
            $DB->set_field('course', 'fullname', $fn, ['id' => $targetid]);
        }
        if (array_key_exists('csv_idnumber', $pair)) {
            $DB->set_field('course', 'idnumber', (string) ($pair['csv_idnumber'] ?? ''), ['id' => $targetid]);
        }

        $shortcsv = trim((string) ($pair['csv_shortname'] ?? ''));
        $tok = bulk_course_schedule::year_token_from_text($shortcsv);
        if ($tok === null) {
            $t = $DB->get_record('course', ['id' => $targetid], 'shortname', IGNORE_MISSING);
            if ($t && $t->shortname !== '') {
                $tok = bulk_course_schedule::year_token_from_text($t->shortname);
            }
        }
        bulk_course_schedule::apply_year_from_source($targetid, $sourceid, $tok);
    }
}
