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

use block_courseimport\job;
use core\url;

/**
 * Builds template context fragments for bulk/results.php.
 */
final class bulk_results_view {

    /**
     * Rows for the child-jobs table in bulk_status template.
     *
     * @param array<int, job> $childrecords Child jobs already filtered by the caller query.
     * @return array<int, array<string, mixed>>
     */
    public static function build_child_job_table_rows(array $childrecords): array {
        $formatter = \core\di::get(\core\formatting::class);
        $rows = [];
        foreach ($childrecords as $child) {
            $status = (string) $child->status;
            $targetname = (string) $child->targetname;
            $sourcename = (string) $child->sourcename;
            $targetcourseid = (int) $child->target;
            $sourcecourseid = (int) $child->source;
            $targetcontext = \context_course::instance($targetcourseid, IGNORE_MISSING);
            $sourcecontext = \context_course::instance($sourcecourseid, IGNORE_MISSING);
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
                'sourceid' => $sourcecourseid,
                'statelabel' => job::format_status_label($status),
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
     * @return array<int, array{url: string, label: string, count: int, filterkey: string, current: bool}>
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
                'url' => (new url($base, ['bulkid' => $bulkid, 'page' => 0, 'completed' => 0]))->out(false),
                'label' => get_string('bulkshowallchildjobs', 'block_courseimport'),
                'count' => $countall,
                'filterkey' => 'all',
                'current' => !$completedonly,
            ],
            [
                'url' => (new url($base, ['bulkid' => $bulkid, 'page' => 0, 'completed' => 1]))->out(false),
                'label' => get_string('bulkshowcompletedchildjobs', 'block_courseimport'),
                'count' => $countfinished,
                'filterkey' => 'finished',
                'current' => $completedonly,
            ],
        ];
    }
}
