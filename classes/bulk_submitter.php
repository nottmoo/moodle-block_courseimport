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
 * Creates queued import jobs from resolved source/target pairs.
 *
 * Both source and target courses must already exist; this class never creates courses.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_submitter {
    /**
     * Submits resolved pairs and queues import jobs under a bulk job.
     *
     * @param array<int, array<string, mixed>> $pairs Resolved pairs from {@see module_pair_resolver::resolve()}.
     * @param int $userid User submitting the bulk batch.
     * @return array{bulkjob: bulk_job, created: int, skipped: int, failed: int, failures: array<int, array<string, mixed>>, skips: array<int, array<string, mixed>>}
     */
    public static function submit(array $pairs, int $userid): array {
        global $DB;

        $bulkjob = new bulk_job($userid);
        $bulkjob->save();

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $failures = [];
        $skips = [];

        $transaction = $DB->start_delegated_transaction();
        foreach ($pairs as $pair) {
            $source = (int) ($pair['source_id'] ?? 0);
            $target = (int) ($pair['target_id'] ?? 0);
            if ($source <= 0 || $target <= 0) {
                $failed++;
                $failures[] = [
                    'target_id' => $target,
                    'source_id' => $source,
                    'error' => get_string('bulkunknownerror', 'block_courseimport'),
                ];
                continue;
            }
            $skipreason = job::bulk_skip_reason($target, $source);
            if ($skipreason !== null) {
                $skipped++;
                $skips[] = [
                    'target_id' => $target,
                    'source_id' => $source,
                    'csv_shortname' => $pair['csv_shortname'] ?? '',
                    'reason' => $skipreason,
                ];
                continue;
            }
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

        // Parent counters track queued child jobs only; pre-queue failures stay in $failures / notifications.
        $bulkjob->set_counts($created, 0, 0);
        $bulkjob->apply_status_after_submit($created, $skipped);

        $transaction->allow_commit();

        return [
            'bulkjob' => $bulkjob,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'failures' => $failures,
            'skips' => $skips,
        ];
    }
}
