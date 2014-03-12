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

require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');

/**
 * Defines class for course appointments block
 *
 * @package block_courseimport
 * @author      Yijun Xue
 * @copyright   University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class new_import_course_search extends import_course_search
{
    /**
     * Search course with similar shortname but same code
     */
    /*final public function searchshortname($coursecode, $shortnamestr) {
        global $DB;
        global $COURSE;
        $contextlevel = 50;

        if ((!is_null($this->shortnameresults)) or (empty($coursecode))) {
            return $this->shortnameresults;
        }
        $this->shortnameresults = array();

        $searchsql = 'select c.id,c.fullname,c.shortname,c.visible,c.sortorder ,
        ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel,
        ctx.instanceid AS ctxinstance
        FROM {course} c
        LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = 50)
        where ((LOWER(c.shortname) LIKE (\'%' . $coursecode . '%\')) and (c.shortname <> \'' . $shortnamestr . '\') and (c.id <> ' . SITEID . '))';
        //To count only use this: $countsql = 'select count(*) as cid FROM {course} c where ((LOWER(c.shortname) LIKE (\' %' . $coursecode . '%\')) AND (c.shortname <> \'' . $str . ' \')) AND (c.id <> SITEID)';
        $resultsetshortname = $DB->get_records_sql($searchsql);
        foreach ($resultsetshortname as $result) {
            $this->shortnameresults[$result->id] = $result;
        }
        return count($resultsetshortname); //$this->shortnameresultscount;
    }*/

    /**
     *
     * @global moodle_database $DB
     */
    protected function get_searchsql() {
        global $DB;
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params = array(
            'fullnamesearch' => '%' . $this->get_search() . '%',
            'shortnamesearch' => '%' . $this->get_search() . '%',
            'siteid' => SITEID,
            'contextlevel' => CONTEXT_COURSE
        );
        $select = " SELECT c.id,c.fullname,c.shortname,c.visible,c.sortorder, ";
        $from = " FROM {course} c ";
        $where = " WHERE (" . $DB->sql_like('c.fullname', ':fullnamesearch', false) . " OR " . $DB->sql_like('c.shortname', ':shortnamesearch', false) . ") AND c.id <> :siteid";
        $orderby = " ORDER BY c.id DESC"; //$orderby    = " ORDER BY c.sortorder";

        if ($this->currentcourseid !== null && !$this->includecurrentcourse) {
            $where .= " AND c.id <> :currentcourseid";
            $params['currentcourseid'] = $this->currentcourseid;
        }

        return array($select . $ctxselect . $from . $ctxjoin . $where . $orderby, $params);
    }
}
