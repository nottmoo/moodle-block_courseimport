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

/**
 * Presentation data for bulk results pages (unit-testable).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_courseimport\local;

use block_courseimport\bulk_job;
use block_courseimport\job;
use core\url;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds template context fragments for bulk/results.php.
 */
final class bulk_results_view {

    /**
     * Counts child import jobs by coarse lifecycle bucket.
     *
     * @param array<int, \stdClass> $childrecords Rows from {@see bulk_job::get_import_jobs_for_bulk_job()}.
     * @return \stdClass Object with int fields: active, finished, failed.
     */
    public static function summarize_child_import_states(array $childrecords): \stdClass {
        return bulk_job::summarize_child_import_states($childrecords);
    }

    /**
     * Rows for the child-jobs table in bulk_status template.
     *
     * @param array<int, \stdClass> $childrecords
     * @param bool $completedonly If true, only finished child jobs are included.
     * @return array<int, array<string, mixed>>
     */
    public static function build_child_job_table_rows(array $childrecords, bool $completedonly): array {
        $formatter = \core\di::get(\core\formatting::class);
        $rows = [];
        foreach ($childrecords as $child) {
            if ($completedonly && $child->status !== job::STATE_FINISHED) {
                continue;
            }
            $targetname = $child->targetname ?? '';
            $sourcename = $child->sourcename ?? '';
            $targetcontext = \context_course::instance($child->target, IGNORE_MISSING);
            $sourcecontext = \context_course::instance($child->source, IGNORE_MISSING);
            $targetcourseid = (int) $child->target;
            $targetlabel = $targetcontext
                ? $formatter->format_string($targetname, true, $targetcontext)
                : get_string('bulkcoursedeleted', 'block_courseimport');
            $targetlinkurl = ($targetcontext && $targetcourseid > 0)
                ? (new url('/course/view.php', ['id' => $targetcourseid]))->out(false)
                : '';
            $rows[] = [
                'target' => $targetlabel,
                'targetid' => $targetcourseid,
                'targetlinkurl' => $targetlinkurl,
                'source' => $sourcecontext
                    ? $formatter->format_string($sourcename, true, $sourcecontext)
                    : get_string('bulkcoursedeleted', 'block_courseimport'),
                'sourceid' => (int) $child->source,
                'statelabel' => self::job_status_label($child->status),
            ];
        }
        return $rows;
    }

    /**
     * Child-job list filters: standard text links with counts (plugins-overview style), not badge buttons.
     *
     * @param int $bulkid Parent bulk job id.
     * @param bool $completedonly Whether the "completed imports only" filter is active.
     * @param int $countall Number of child jobs (all statuses).
     * @param int $countfinished Number of child jobs in {@see job::STATE_FINISHED}.
     * @return array<int, array{url: string, label: string, count: int, current: bool}>
     */
    public static function child_job_filter_items(
        int $bulkid,
        bool $completedonly,
        int $countall,
        int $countfinished
    ): array {
        $base = '/blocks/courseimport/bulk/results.php';
        return [
            [
                'url' => (new url($base, ['bulkid' => $bulkid, 'cpage' => 0, 'completed' => 0]))->out(false),
                'label' => get_string('bulkshowallchildjobs', 'block_courseimport'),
                'count' => $countall,
                'current' => !$completedonly,
            ],
            [
                'url' => (new url($base, ['bulkid' => $bulkid, 'cpage' => 0, 'completed' => 1]))->out(false),
                'label' => get_string('bulkshowcompletedchildjobs', 'block_courseimport'),
                'count' => $countfinished,
                'current' => $completedonly,
            ],
        ];
    }

    /**
     * Label for a child import job status.
     *
     * @param string $status One of {@see job} state constants.
     * @return string
     */
    public static function job_status_label(string $status): string {
        switch ($status) {
            case job::STATE_FINISHED:
                return get_string('privacy:export:status:finished', 'block_courseimport');
            case job::STATE_FAILED:
                return get_string('privacy:export:status:failed', 'block_courseimport');
            case job::STATE_PROCESSING:
                return get_string('privacy:export:status:processing', 'block_courseimport');
            case job::STATE_WAITING:
                return get_string('privacy:export:status:waiting', 'block_courseimport');
            default:
                return get_string('privacy:export:status:unknown', 'block_courseimport');
        }
    }
}
