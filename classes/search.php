<?php
// This file is part of  courseimport block in Moodle - http://moodle.org/
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

use context_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . "/backup/util/interfaces/checksumable.class.php");
require_once($CFG->dirroot . '/backup/backup.class.php');
require_once($CFG->dirroot . '/backup/util/ui/base_ui.class.php');
require_once($CFG->dirroot . "/backup/backup.class.php");
require_once($CFG->dirroot . "/backup/util/ui/backup_ui.class.php");
require_once($CFG->dirroot . '/backup/util/ui/base_ui_stage.class.php');
require_once($CFG->dirroot . '/backup/util/ui/backup_ui_stage.class.php');
require_once($CFG->dirroot . '/backup/util/ui/restore_ui_components.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');

/**
 * Defines class for course import block
 *
 * @package block_courseimport
 * @author      Yijun Xue
 * @copyright   University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search extends \import_course_search {
    /**
     * The results of the search
     * @var array|null
     */
    private $shortnameresults = null;

    /**
     * Returns an array of results from the search.
     *
     * @param string $coursecode - fragment of a shortname to match.
     * @param string $shortnamestr - the short name of a course that should not be returned.
     * @return array - course records that match the coursecode, but not the shortnamestr
     */
    public function get_shortnameresults($coursecode, $shortnamestr) {
        $queryhash = md5($coursecode.'---@---'.$shortnamestr); // Used to check if the query has been done before.
        if (!isset($this->shortnameresults[$queryhash])) {
            $this->searchshortname($coursecode, $shortnamestr);
        }
        return $this->shortnameresults[$queryhash];
    }

    /**
     * Case insensative search for courses with similar shortname but same code.
     *
     * @global \moodle_database $DB
     * @param string $coursecode - fragment of a shortname to match.
     * @param string $shortnamestr - the short name of a course that should not be returned.
     * @return int - the number of results found.
     */
    public function searchshortname($coursecode, $shortnamestr) {
        global $DB;
        $queryhash = md5($coursecode.'---@---'.$shortnamestr);
        if ((isset($this->shortnameresults[$queryhash]))) {
            return count($this->shortnameresults[$queryhash]);
        }
        $this->shortnameresults = array();
        $params = array(
            'shortnamestr' => strtolower($shortnamestr),
            'siteid' => SITEID,
            'coursecode' => strtolower($coursecode).'%',
            );

        $likesql = $DB->sql_like('LOWER(c.shortname)', ':coursecode');
        $searchsql = 'SELECT c.id, c.fullname, c.shortname, c.visible, c.sortorder,
        ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel,
        ctx.instanceid AS ctxinstance
        FROM {course} c
        LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = 50)
        WHERE (('.$likesql.') and (LOWER(c.shortname) <> :shortnamestr) and (c.id <> :siteid))';
        $resultsetshortname = $DB->get_records_sql($searchsql, $params);
        $this->shortnameresults[$queryhash] = $resultsetshortname;
        return count($resultsetshortname);
    }

    /**
     * Create search SQL
     *
     * @global \moodle_database $DB
     * @return array sql and parameters
     */
    protected function get_searchsql() {
        global $DB, $COURSE;
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params = array(
            'siteid' => SITEID,
            'contextlevel' => CONTEXT_COURSE
        );
        // If no search string supplied use target course short name.
        $shortnamesearch = $this->get_search();
        if (empty($shortnamesearch)) {
            $shortnamestring = strtolower($COURSE->shortname);
            if (strlen($shortnamestring) > 4) {
                $shortnamehyphen = strpos($shortnamestring, '-');
                // Saturn / Non Saturn courses should have a hyphen.
                if ($shortnamehyphen !== false) {
                    $shortnamesearch = substr($shortnamestring, 0, $shortnamehyphen);
                }
                // Non saturn courses require a more specific short name.
                if (strlen($shortnamesearch) < 4 and $shortnamehyphen) {
                    $shortnamesearch = substr($shortnamestring,0,-4);
                }
            } else {
                // Should not get here if course names in the db are in the right format
                // If we do bottle out as we dont want to do a bad query
                $params = array();
                $select = "SELECT NULL from {course} where FALSE";
                return array($select, $params);
            }
            $params['shortnamesearch'] = $shortnamesearch . '%';
            $where = " WHERE (" . $DB->sql_like('c.shortname', ':shortnamesearch', false) . ") AND c.id <> :siteid";
        } else {
            $params['fullnamesearch'] = '%'. $this->get_search() . '%';
            $params['shortnamesearch'] = '%' . $shortnamesearch . '%';
            $where = " WHERE (" . $DB->sql_like('c.fullname', ':fullnamesearch', false) . " OR " .
                        $DB->sql_like('c.shortname', ':shortnamesearch', false) . ") AND c.id <> :siteid";
        }
        $select = " SELECT c.id,c.fullname,c.shortname,c.visible,c.sortorder, ";
        $from = " FROM {course} c ";
        $orderby = " ORDER BY c.id DESC";
        if ($this->currentcourseid !== null && !$this->includecurrentcourse) {
            $where .= " AND c.id <> :currentcourseid";
            $params['currentcourseid'] = $this->currentcourseid;
        }
        return array($select . $ctxselect . $from . $ctxjoin . $where . $orderby, $params);
    }
}
