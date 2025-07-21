<?php
// This file is part of courseimport block in Moodle Moodle - http://moodle.org/
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

use block_courseimport\search;
use context_course;
use local_uonlib\course_utils;
use block_courseimport\job;
use core\url;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/backup/util/ui/renderer.php');

/**
 * This course import backup and restore output renderers
 *
 * @package   block_courseimport
 * @copyright University of Nottingham
 * @author    Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \core_backup_renderer {
    /**
     * Renders an import course search object
     *
     * @param \block_courseimport\search $component
     * @return string
     */
    public function render_search(search $component) {
        global $COURSE;

        $data = new \stdClass();
        $data->courses = [];
        $data->resultcount = $component->get_count();
        $data->hasinaccesibile = false;
        $data->othercourses = [];
        $data->searchname = search::$VAR_SEARCH;
        $data->searchvalue = $component->get_search();

        $coursedetails = course_utils::get_module_details($COURSE);

        if ($coursedetails && $coursedetails['modulecode']) {
            $modulecode = $coursedetails['modulecode'];
            $yearcode  = $coursedetails['yearcode'];
            $colist = $component->get_shortnameresults($modulecode, $COURSE->shortname);
        } else {
            $colist = null;
            $modulecode = null;
            $yearcode  = null;
        }

        $highlight = false;

        $data->nomatching = ($component->get_count() === 0);

        if (!$data->nomatching) {
            foreach ($component->get_results() as $course) {
                $context = context_course::instance($course->id);
                $coursedata = [
                    'id' => $course->id,
                    'visible' => $course->visible,
                    'highlight' => false,
                    'fullname' => format_string($course->fullname, true, ['context' => $context]),
                    'shortname' => format_string($course->shortname, true, ['context' => $context]),
                ];

                if (!is_null($colist) && array_key_exists($course->id, $colist)) {
                    // Remove the course from the found course.
                    unset($colist[$course->id]);
                }

                if ($course->id == $COURSE->id) {
                    // Do not display the course we are in.
                    continue;
                }

                $moduledetail = course_utils::get_module_details($course);
                $thisyearcode = $moduledetail['yearcode'];

                if (!$highlight && $moduledetail['modulecode'] === $modulecode && (int) $thisyearcode < (int) $yearcode) {
                    // This is an older version of the course, put it at the start of the list.
                    $highlight = true;
                    $coursedata['highlight'] = true;
                    array_unshift($data->courses, (object) $coursedata);
                } else {
                    $data->courses[] = (object) $coursedata;
                }
            }

            array_splice($data->courses, 100);
        }

        $searchstr = trim(optional_param('search', '', PARAM_TEXT));

        // Course list for shortname search is not treated as original course list.
        if (!is_null($colist) && count($colist) > 0 && $searchstr === "") {
            $data->hasinaccesibile = true;
            $data->resultcount += count($colist);

            foreach ($colist as $course) {
                $context = context_course::instance($course->id);
                $data->othercourses[] = (object) [
                    'id' => $course->id,
                    'visible' => $course->visible,
                    'highlight' => false,
                    'fullname' => format_string($course->fullname, true, ['context' => $context]),
                    'shortname' => format_string($course->shortname, true, ['context' => $context]),
                ];
            }
        }

        return $this->render_from_template('block_courseimport/course_search', $data);
    }

    /**
     * Renders a progress object.
     *
     * @param \block_courseimport\output\progress $progress
     * @return string
     * @throws \moodle_exception
     */
    public function render_progress(progress $progress): string {
        $data = $progress->export_for_template($this);
        return $this->render_from_template('block_courseimport/import_status', $data);
    }

    /**
     * Displays the progress of an import.
     *
     * @param job $job The import job that is being processed.
     * @param int $courseid The database id of the course.
     * @return string
     */
    public function display_import_progress(job $job, int $courseid): string {
        $courseurl = new url('/course/view.php', ['id' => $courseid]);
        $progresssetup = [
                'backupid' => $job->id,
                'courseurl' => $courseurl->out(),
        ];
        return $this->render_from_template('block_courseimport/import_status', $progresssetup);
    }
}
