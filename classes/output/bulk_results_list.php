<?php
// This file is part of the courseimport block in Moodle
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

namespace block_courseimport\output;

use block_courseimport\bulk_job;
use block_courseimport\local\bulk_progress;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core\url;

defined('MOODLE_INTERNAL') || die();

/**
 * Bulk job list on {@see results.php} (no bulkid): table + paging.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_results_list implements renderable, templatable {

    /** @var array<int, \stdClass> */
    private array $jobs;
    private int $totalcount;
    private int $page;
    private int $perpage;
    private url $listbaseurl;

    /**
     * @param array<int, \stdClass> $jobs Parent bulk job rows for this page
     */
    private function __construct(
        array $jobs,
        int $totalcount,
        int $page,
        int $perpage,
        url $listbaseurl
    ) {
        $this->jobs = $jobs;
        $this->totalcount = $totalcount;
        $this->page = $page;
        $this->perpage = $perpage;
        $this->listbaseurl = $listbaseurl;
    }

    /**
     * Load list state and set page URL (list view).
     *
     * @param int $userid Current user id
     * @param int $page Page index for parent-job list
     * @param int $perpage Rows per page
     * @return self
     */
    public static function fetch(int $userid, int $page, int $perpage): self {
        global $PAGE;

        $totalcount = bulk_job::count_for_user($userid);
        $jobs = bulk_job::list_for_user_page($userid, $perpage, $page);
        $listbaseurl = new url('/blocks/courseimport/bulk/results.php');
        $PAGE->set_url($listbaseurl, ['page' => $page]);

        return new self($jobs, $totalcount, $page, $perpage, $listbaseurl);
    }

    /**
     * @param renderer_base $output
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        $jobrows = [];
        foreach ($this->jobs as $bj) {
            $listtotalunits = (int) $bj->total_count;
            $listcompletedunits = (int) $bj->completed_count + (int) $bj->failed_count;
            $listprogresspercent = bulk_progress::percentage_complete($listcompletedunits, $listtotalunits);
            $viewurl = (new url('/blocks/courseimport/bulk/results.php', ['bulkid' => $bj->id]))->out(false);
            $jobrows[] = [
                'idlabel' => '#' . (int) $bj->id,
                'total' => (int) $bj->total_count,
                'completed' => (int) $bj->completed_count,
                'failed' => (int) $bj->failed_count,
                'status' => $bj->status,
                'progressdisplay' => $listprogresspercent . '%',
                'viewlink' => \html_writer::link($viewurl, get_string('bulkstatusview', 'block_courseimport')),
            ];
        }

        $hasjobs = count($jobrows) > 0;
        $from = $this->page * $this->perpage + 1;
        $to = $this->page * $this->perpage + count($jobrows);

        $paginationtext = '';
        $pagingtop = '';
        $pagingbottom = '';
        if ($hasjobs) {
            $paginationtext = get_string('bulkpagination', 'block_courseimport', (object) [
                'from' => $from,
                'to' => $to,
                'total' => $this->totalcount,
            ]);
            $pagingtop = $output->paging_bar($this->totalcount, $this->page, $this->perpage, $this->listbaseurl);
            $pagingbottom = $pagingtop;
        }

        return [
            'heading' => get_string('bulkstatuslistheading', 'block_courseimport'),
            'hasjobs' => $hasjobs,
            'paginationtext' => $paginationtext,
            'pagingtop' => $pagingtop,
            'pagingbottom' => $pagingbottom,
            'tableheaders' => [
                get_string('bulkstatuscolumnd', 'block_courseimport'),
                get_string('bulkstatustotal', 'block_courseimport'),
                get_string('bulkstatuscompleted', 'block_courseimport'),
                get_string('bulkstatusfailed', 'block_courseimport'),
                get_string('bulkstatusstate', 'block_courseimport'),
                get_string('bulkstatusprogress', 'block_courseimport'),
                '',
            ],
            'jobrows' => $jobrows,
            'emptymessage' => get_string('bulkstatusnone', 'block_courseimport'),
        ];
    }
}
