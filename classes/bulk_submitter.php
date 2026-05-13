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

use local_uonlib\course_utils;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

/**
 * Creates queued import jobs from resolved source/target pairs.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_submitter {
    /**
     * Submits resolved pairs: creates pending targets if needed, then queues import jobs under a bulk job.
     *
     * @param array<int, array<string, mixed>> $pairs Resolved pairs from {@see module_pair_resolver::resolve()}.
     * @param int $userid User submitting the bulk batch.
     * @return array{bulkjob: bulk_job, created: int, failed: int, failures: array<int, array<string, mixed>>}
     */
    public static function submit(array $pairs, int $userid): array {
        global $DB;

        $bulkjob = new bulk_job($userid);
        $bulkjob->save();
        $bulkjob->set_counts(count($pairs), 0, 0);

        $created = 0;
        $failed = 0;
        $failures = [];

        // Phase 1: Create any pending target courses OUTSIDE a transaction.
        // create_course() fires Moodle events that interact poorly with delegated transactions.
        $resolvedpairs = [];
        foreach ($pairs as $pair) {
            $source = (int)($pair['source_id'] ?? 0);
            $target = (int)($pair['target_id'] ?? 0);
            if ($source <= 0) {
                $failed++;
                $failures[] = ['target_id' => $target, 'source_id' => $source, 'error' => 'Invalid source id'];
                continue;
            }
            if (!empty($pair['pending_create'])) {
                try {
                    $target = self::create_target_from_csv_row($pair, $source, $userid);
                    $pair['target_id'] = $target;
                    unset($pair['pending_create']);
                } catch (\Throwable $e) {
                    $failed++;
                    $failures[] = [
                        'target_id'     => $target,
                        'source_id'     => $source,
                        'csv_shortname' => $pair['csv_shortname'] ?? '',
                        'csv_fullname'  => $pair['csv_fullname']  ?? '',
                        'error'         => $e->getMessage(),
                    ];
                    continue;
                }
            }
            if ($target <= 0) {
                $failed++;
                $failures[] = ['target_id' => $target, 'source_id' => $source, 'error' => 'Invalid target id'];
                continue;
            }
            $resolvedpairs[] = $pair;
        }

        // Phase 2: Queue import jobs inside a transaction (no course creation here).
        $transaction = $DB->start_delegated_transaction();
        foreach ($resolvedpairs as $pair) {
            $source = (int)($pair['source_id'] ?? 0);
            $target = (int)($pair['target_id'] ?? 0);
            try {
                $backupid = bulk_backup_helper::create_backup_controller($source, $userid);
                $job = new job($source, $target, $backupid, $userid, $bulkjob->id);
                $job->save();
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $failures[] = ['target_id' => $target, 'source_id' => $source, 'error' => $e->getMessage()];
            }
        }

        $bulkjob->set_counts(count($pairs), 0, $failed);
        if ($created > 0 && $failed > 0) {
            $bulkjob->set_status(bulk_job::STATUS_PARTIAL);
        } else if ($created > 0) {
            $bulkjob->set_status(bulk_job::STATUS_QUEUED);
        } else {
            $bulkjob->set_status(bulk_job::STATUS_FAILED);
        }

        $transaction->allow_commit();

        return [
            'bulkjob' => $bulkjob,
            'created' => $created,
            'failed' => $failed,
            'failures' => $failures,
        ];
    }

    /**
     * Creates a new Moodle course from CSV labels for rows queued with a new target (pending_create in the pair).
     *
     * Reuses an existing course if the shortname already exists. Start/end dates come from the new shortname only
     * ({@see course_utils::calculate_startdate()} / {@see course_utils::calculate_enddate()}).
     *
     * @param array<string, mixed> $pair Resolved pair with csv_fullname, csv_shortname, csv_idnumber, etc.
     * @param int $sourcecourseid Source course id (used for default category when plugin default is unset).
     * @param int $userid User creating the course; enrolled as editingteacher when possible.
     * @return int New or existing target course id.
     * @throws \moodle_exception On invalid row data or missing category.
     */
    protected static function create_target_from_csv_row(array $pair, int $sourcecourseid, int $userid): int {
        global $DB;

        $fullname  = trim((string)($pair['csv_fullname']  ?? ''));
        $shortname = trim((string)($pair['csv_shortname'] ?? ''));
        $idnumber  = trim((string)($pair['csv_idnumber']  ?? ''));

        if ($fullname === '' || $shortname === '') {
            throw new \moodle_exception('bulkinvalidcreaterow', 'block_courseimport');
        }

        // If a course with this shortname already exists, reuse it as the target.
        $existing = $DB->get_record('course', ['shortname' => $shortname], 'id', IGNORE_MISSING);
        if ($existing) {
            return (int) $existing->id;
        }

        // Resolve category: source course category → Moodle site default → first available.
        $sourcecourse = $DB->get_record('course', ['id' => $sourcecourseid], 'id, category', IGNORE_MISSING);
        $catid = ($sourcecourse && (int) $sourcecourse->category > 0) ? (int) $sourcecourse->category : 0;
        if ($catid < 1) {
            $catid = (int) get_config('moodlecourse', 'defaultcategory');
        }
        // Verify the category actually exists in the DB.
        if ($catid < 1 || !$DB->record_exists('course_categories', ['id' => $catid])) {
            $firstcat = $DB->get_record_select('course_categories', 'id > 0', [], 'id', IGNORE_MULTIPLE);
            $catid = $firstcat ? (int) $firstcat->id : 0;
        }
        if ($catid < 1) {
            throw new \moodle_exception('bulknocategory', 'block_courseimport');
        }

        $data                 = new \stdClass();
        $data->category       = $catid;
        $data->fullname       = $fullname;
        $data->shortname      = $shortname;
        $data->idnumber       = $idnumber;
        $data->summary        = '';
        $data->summary_format = FORMAT_MOODLE;
        $data->format         = 'topics';
        $data->numsections    = 1;
        $data->visible        = 1;
        // Match enrol_nottingham\course_helper::create_course(): academic dates from target shortname only
        $data->startdate = course_utils::calculate_startdate($shortname);
        $data->enddate = course_utils::calculate_enddate($shortname);

        $course = create_course($data);
        $newid  = (int) $course->id;

        // Enroll the creator as editingteacher so the course appears in "My courses".
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        if ($roleid) {
            enrol_try_internal_enrol($newid, $userid, $roleid);
        }

        return $newid;
    }
}
