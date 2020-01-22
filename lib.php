<?php
// This file is part of courseimport block in Moodle - http://moodle.org/
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

defined('MOODLE_INTERNAL') || die();

/**
 * Check if the current time is within a time period.
 *
 * @param string $start Start time for the period
 * @param string $end End time of the period
 * @return bool
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
