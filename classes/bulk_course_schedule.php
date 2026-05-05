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

namespace block_courseimport;

use local_uonlib\course_utils;

defined('MOODLE_INTERNAL') || die();

/**
 * Start/end date handling for new bulk target courses (year token from admin upload form).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_course_schedule {
    /**
     * Copy start/end from source course, shifting the calendar year to match the token (day/month/time preserved per field).
     *
     * @param int $targetcourseid
     * @param int $sourcecourseid
     * @param string|null $yeartoken
     * @return void
     */
    public static function apply_year_from_source(int $targetcourseid, int $sourcecourseid, ?string $yeartoken): void {
        global $DB;
        if ($yeartoken === null || trim($yeartoken) === '') {
            return;
        }
        $newyear = self::parse_calendar_year($yeartoken);
        if ($newyear === null) {
            return;
        }
        $src = $DB->get_record('course', ['id' => $sourcecourseid], 'startdate,enddate', IGNORE_MISSING);
        if (!$src) {
            return;
        }
        $start = self::shift_timestamp_year((int) $src->startdate, $newyear);
        $end = self::shift_timestamp_year((int) $src->enddate, $newyear);
        $DB->set_field('course', 'startdate', $start, ['id' => $targetcourseid]);
        $DB->set_field('course', 'enddate', $end, ['id' => $targetcourseid]);
    }

    /**
     * Parses a supported year token into a four-digit calendar year.
     *
     * @param string $token e.g. "2025", "25-26", "2526"
     * @return int|null calendar year for the course start
     */
    public static function parse_calendar_year(string $token): ?int {
        $t = trim($token);
        if ($t === '') {
            return null;
        }
        if (preg_match('/^(19|20)\d{2}$/', $t)) {
            return (int) $t;
        }
        $academicyear = str_replace(['-', '/'], '', $t);
        if (preg_match('/^\d{4}$/', $academicyear)) {
            return 2000 + (int) substr($academicyear, 0, 2);
        }
        return null;
    }

    /**
     * Extract an academic year token from shortname/fullname text (e.g. 25-26, 2526 at end).
     *
     * @param string $text
     * @return string|null token for {@see parse_calendar_year}
     */
    public static function year_token_from_text(string $text): ?string {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $yearcode = course_utils::parse_year($text);
        return $yearcode !== '' ? $yearcode : null;
    }

    /**
     * Rebuilds a timestamp with the same month/day/time in the requested year.
     *
     * @param int $timestamp
     * @param int $newyear
     * @return int
     */
    protected static function shift_timestamp_year(int $timestamp, int $newyear): int {
        $tz = \core_date::get_server_timezone_object();
        $d = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($tz);
        $shifted = $d->setDate($newyear, (int) $d->format('n'), (int) $d->format('j'));
        return $shifted->getTimestamp();
    }
}
