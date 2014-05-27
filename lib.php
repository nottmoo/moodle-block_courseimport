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

//define('BLOCK_COURSELIFE_NOTARCHIVED', '0'); define('BLOCK_COURSELIFE_ARCHIVED', '1');

/**
 * findfilesize
 *
 * @param resource id
 * @return fileinfo object
 */
function block_courseimport_findfilesize($id) {
    global $DB;
    $fileinfo = new stdClass;
    $fileinfo->fsize = "";
    $fileinfo->ftype = "";
    $context = null;
    if (!$cm = get_coursemodule_from_id('resource', $id)) {
        return false;
    } else {
        $cm = get_coursemodule_from_id('resource', $id);
        $resource = $DB->get_record('resource', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!

        if (count($files) < 1) {
            resource_print_filenotfound($resource, $cm, $course);
            return false;

        } else {
            $file = reset($files);
            unset($files);
            $fileinfo->fsize = $file->get_filesize();
            $fileinfo->ftype = $file->get_mimetype();
            return $fileinfo;
        }
    }
}

/**
 *  timecheck function : check if time now between param start and end
 *
 * @param string start time
 * @param string start end
 * @return true/false
 */
function block_courseimport_timecheck($start, $end) {
    $time = date("G:i:s");
    $time1 = strtotime($time);
    $resttimefrom = strtotime($start);
    $resttimeto = strtotime($end);
    $midnight = strtotime('midnight');

    if ($resttimefrom < $resttimeto) {
        // When the from time is lower than the to time the current time should be between the values.
        if (($time1 > $resttimefrom ) and ($time1 < $resttimeto)) {
            return true;
        } else {
            return false;
        }
    } else if ($resttimefrom > $resttimeto) {
        // When the from time is greater than the to time the current time should be outside the gap between them.
        if ((($time1 > $resttimefrom)  and ($time1 > $resttimeto)) or (($time1 < $resttimefrom)  and ($time1 < $resttimeto))) {
            return true;
        } else {
            return false;
        }
    } else if ($resttimefrom == $midnight && $resttimeto == $midnight) {
        // Assume midnight - midnight means the whole day.
        return true;
    } else {
        return false; // From and to are equal, assume no time.
    }
}
