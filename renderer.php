<?php
// This file is part of Moodle - http://moodle.org/
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
 * This file contains backup and restore output renderers
 *
 * @package   courseimport
 * @copyright Yijun Xue
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The primary renderer for the backup.
 *
 * Can be retrieved with the following code:
 * <?php
 * $renderer = $PAGE->get_renderer('core','backup');
 * ?>
 *
 * @copyright 2010 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/blocks/courseimport/lib.php');
require_once($CFG->dirroot . '/backup/util/ui/renderer.php');

class block_courseimport_renderer extends core_backup_renderer
{
    /**
     * Renderers a progress bar for the backup or restore given the items that
     * make it up.
     * @param array $items An array of items
     * @return string
     */
    public function progress_bar(array $items) {
        global $OUTPUT;
        $filefilter='';
        $setsize = get_config('block_courseimport', 'filesize');
        $fnotice = get_string('filternotice', 'block_courseimport', array('size' => $setsize));

        $filefilter .= parent::progress_bar($items);
        $filefilter .= $OUTPUT->notification($fnotice, 'notifymessage');
        return $filefilter ;
    }

    /**
     * Renders an import course search object
     *
     * @param import_course_search $component
     * @return string
     */
    public function render_block_courseimport_search(block_courseimport_search $component) {
        global $COURSE ,$OUTPUT;
        $url = $component->get_url();
        $output = html_writer::start_tag('div', array('class' => 'import-course-search'));
        if ($component->get_count() === 0) {
            $output .= $this->output->notification(get_string('nomatchingcourses', 'backup'));

            $output .= html_writer::start_tag('div', array('class' => 'ics-search'));
            $output .= html_writer::empty_tag('input', array('type' => 'text', 'name' => block_courseimport_search::$VAR_SEARCH, 'value' => $component->get_search()));
            $output .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'searchcourses', 'value' => get_string('search')));
            $output .= html_writer::end_tag('div');

            $output .= html_writer::end_tag('div');
            return $output;
        }
        $output .= html_writer::tag('div', get_string('totalcoursesearchresults', 'backup', $component->get_count()));
        $output .= html_writer::start_tag('div', array('class' => 'ics-results'));
        $table = new html_table();
        $table->head = array('', get_string('shortnamecourse'), get_string('fullnamecourse'), "course ID");
        $table->data = array();
        $shortnamestr = strtolower($COURSE->shortname);
        $coursecode = strtolower(substr($shortnamestr, 0, strpos($shortnamestr, '-')));

        $yearcode = substr($shortnamestr, -4);
        $isyearnum =is_numeric($yearcode);


        $colist = null;
        if(strlen($coursecode) >= 4) {
            $colist = $component->get_shortnameresults($coursecode, $shortnamestr);
        } else {
            //$coursecode = "unknowcourseshortname";
            $coursecode = substr($shortnamestr, 0, -4);// This is for non-saturn course.
        }

        $highlightguard = true;
        $highlight = false;
        $cids = array();

        foreach ($component->get_results() as $course) {

            $cid = $course->id;
            //$cids[]=$course->id;
            if ((! is_null($colist)) and (array_key_exists($cid, $colist))) {
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
            $cshortname = strtolower($course->shortname);
            $thisyearcode = substr($cshortname, -4); // a year cord for other course
            $isthisyearnum =is_numeric($thisyearcode);
            $uuu = strpos($cshortname, $coursecode);

            //check if thisyearcode > yearcode for select
            if (($uuu === 0) and $isthisyearnum and $isyearnum and !$highlight) {
                if ( (int)$thisyearcode < (int)$yearcode ) { // this course is old course with same code
                    $highlight = true;
                }
            }
            //echo "===" . $uuu ."===" . $cshortname ."===" . $coursecode . "=====" . $thisyearcode . "===== $highlight ======" . $yearcode . "</br>";
            if (($highlight === true) and ($highlightguard === true)) {
                $cshortname = html_writer::tag('strong', format_string($cshortname, true, array('context' => context_course::instance($cid))));
                $row->cells = array(
                    //html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'importid', 'value' => $cid, 'checked'=>1)),
                    html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'importid', 'value' => $cid, 'checked' => 'checked')),
                    $cshortname,
                    html_writer::tag('strong', format_string($course->fullname, true, array('context' => context_course::instance($cid)))),
                    html_writer::tag('strong', format_string($cid, true, array('context' => context_course::instance($cid))))
                );
                array_unshift($table->data, $row);
                $highlightguard = false;
            } else {
                $row->cells = array(
                    html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'importid', 'value' => $cid)),
                    format_string($cshortname, true, array('context' => context_course::instance($cid))),
                    format_string($course->fullname, true, array('context' => context_course::instance($cid))),
                    format_string($cid, true, array('context' => context_course::instance($cid)))
                );
                $table->data[] = $row;
            }
            //$table->data[] = $row;
        }
        array_splice($table->data, 100);

        if (empty($_REQUEST['search'])) {
            $searchstr = "";
        } else {
            $searchstr=trim($_REQUEST["search"]);
        }

            //echo "<input type='hidden' value='-----$searchstr----' name='checkserver'>";
        // course list for shortname search is not treat as original course list
        if ((! is_null($colist)) and (count($colist) >0) and ($searchstr === "")) {
            // if need more infor user this $strhelp = $OUTPUT->help_icon('clisthelp','block_courseimport');
            $askroleinfo = get_string('askroleinfo', 'block_courseimport');
            $inforcell = new html_table_cell($OUTPUT->notification($askroleinfo, 'notifyproblem'));
            $inforcell->colspan = 3;
            $table->data[] = new html_table_row(array($inforcell));
            foreach ($colist as $id => $course) {
                $row = new html_table_row();
                $row->attributes['class'] = 'ics-course';
                if (!$course->visible) {
                    $row->attributes['class'] .= ' dimmed';
                }
                $row->cells = array(
                    //html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'importid', 'value' => $id, 'disabled' => 'disabled')),
                    "",
                    format_string($course->shortname, true, array('context' => context_course::instance($id))),
                    format_string($course->fullname, true, array('context' => context_course::instance($id))),
                    $id
                );
                $table->data[] = $row;
            }
        }

        $output .= html_writer::table($table);
        $output .= html_writer::end_tag('div');

        $output .= html_writer::start_tag('div', array('class' => 'ics-search'));
        $output .= html_writer::empty_tag('input', array('type' => 'text', 'name' => block_courseimport_search::$VAR_SEARCH, 'value' => $component->get_search()));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'searchcourses', 'value' => get_string('search')));
        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('div');
        return $output;
    }

}