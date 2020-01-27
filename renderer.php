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

require_once($CFG->dirroot . '/backup/util/ui/renderer.php');

/**
 * This course import backup and restore output renderers
 *
 * @package   block_courseimport
 * @copyright University of Nottingham
 * @author    Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_courseimport_renderer extends core_backup_renderer {
    /**
     * Renders an import course search object
     *
     * @param import_course_search $component
     * @return string
     */
    public function render_block_courseimport_search(block_courseimport_search $component) {
        global $COURSE;
        $output = html_writer::start_div('import-course-search');

        $output .= html_writer::div(get_string('totalcoursesearchresults', 'backup', $component->get_count()));
        $output .= html_writer::start_div('ics-results');
        $table = new html_table();
        $table->head = array('', get_string('shortnamecourse'), get_string('fullnamecourse'), "course ID");
        $table->data = array();
        $coursedetails = local_uonlib_courselib::get_module_details($COURSE);
        $shortname = $COURSE->shortname;
        $colist = null;
        $modulecode = null;
        $yearcode  = null;
        if ($coursedetails && ($coursedetails['yearcode']) && ($coursedetails['modulecode'])) {
            $modulecode = $coursedetails['modulecode'];
            $yearcode  = $coursedetails['yearcode'];
            $colist = $component->get_shortnameresults($modulecode, $shortname);
        }

        $highlightguard = true;
        $highlight = false;

        if ((!$modulecode) || (!$yearcode) || ($component->get_count() === 0)) {
            $row = new html_table_row();
            $notice = new html_table_cell($this->output->notification(get_string('nomatchingcourses', 'backup')));
            $notice->colspan = 4;
            $row->cells = array($notice);
            $table->data[] = $row;
        } else {
            foreach ($component->get_results() as $course) {
                $cid = $course->id;
                if ((!is_null($colist)) and (array_key_exists($cid, $colist))) {
                    unset($colist[$cid]);
                }
                if ($cid == $COURSE->id) {
                    continue;
                }
                $row = new html_table_row();
                $row->attributes['class'] = 'ics-course';
                if (!$course->visible) {
                    $row->attributes['class'] .= ' dimmed';
                }
                $moduledetail = local_uonlib_courselib::get_module_details($course);
                $thisyearcode = $moduledetail['yearcode'];
                    // Check if thisyearcode > yearcode for select.
                    if (($moduledetail['modulecode'] === $modulecode) && (!$highlight)) {
                        if ((int) $thisyearcode < (int) $yearcode) { // This course is old course with same code.
                            $highlight = true;
                        }
                    }

                if (($highlight === true) and ($highlightguard === true)) {
                    $cshortname = html_writer::tag('strong', format_string($course->shortname, true, array('context' => context_course::instance($cid))));
                    $row->cells = array(
                        html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'importid', 'value' => $cid, 'checked' => 'checked')),
                        $course->shortname,
                        html_writer::tag('strong', format_string($course->fullname, true, array('context' => context_course::instance($cid)))),
                        html_writer::tag('strong', format_string($cid, true, array('context' => context_course::instance($cid))))
                    );
                    array_unshift($table->data, $row);
                    $highlightguard = false;
                } else {
                    $row->cells = array(
                        html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'importid', 'value' => $cid)),
                        format_string($course->shortname, true, array('context' => context_course::instance($cid))),
                        format_string($course->fullname, true, array('context' => context_course::instance($cid))),
                        format_string($cid, true, array('context' => context_course::instance($cid)))
                    );
                    $table->data[] = $row;
                }
            }
            array_splice($table->data, 100);
        }

        if (empty($_REQUEST['search'])) {
            $searchstr = "";
        } else {
            $searchstr = trim($_REQUEST["search"]);
        }

        // Course list for shortname search is not treat as original course list.
        if ((!is_null($colist)) and (count($colist) > 0) and ($searchstr === "")) {
            // If need more infor user this $strhelp = $this->help_icon('clisthelp','block_courseimport').
            $askroleinfo = get_string('askroleinfo', 'block_courseimport');
            $inforcell = new html_table_cell($this->output->notification($askroleinfo, 'notifyproblem'));
            $inforcell->colspan = 4;
            $table->data[] = new html_table_row(array($inforcell));
            foreach ($colist as $id => $course) {
                $row = new html_table_row();
                $row->attributes['class'] = 'ics-course';
                if (!$course->visible) {
                    $row->attributes['class'] .= ' dimmed';
                }
                $row->cells = array(
                    "",
                    format_string($course->shortname, true, array('context' => context_course::instance($id))),
                    format_string($course->fullname, true, array('context' => context_course::instance($id))),
                    $id
                );
                $table->data[] = $row;
            }
        }

        $output .= html_writer::table($table);
        $output .= html_writer::end_div();
        $output .= html_writer::start_div('ics-search');
        $output .= html_writer::empty_tag('input', array('type' => 'text', 'name' => block_courseimport_search::$VAR_SEARCH, 'value' => $component->get_search()));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'searchcourses', 'value' => get_string('search')));
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        return $output;
    }
}
