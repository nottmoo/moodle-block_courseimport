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

defined('MOODLE_INTERNAL') || die();

/**
 * Minimal CSV parser for bulk rollover uploads.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class csv_parser {
    /**
     * @param string $path Absolute filesystem path to CSV
     * @return array<int, array<string, string>> List of associative rows
     */
    public static function parse_file(string $path): array {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return [];
        }
        try {
            return self::parse_from_handle($fh);
        } finally {
            fclose($fh);
        }
    }

    /**
     * Parse CSV from raw file contents (e.g. {@see \moodleform::get_file_content()}), no temp file.
     *
     * @param string $content
     * @return array<int, array<string, string>> List of associative rows
     */
    public static function parse_string(string $content): array {
        if ($content === '') {
            return [];
        }
        $fh = fopen('php://memory', 'r+b');
        if ($fh === false) {
            return [];
        }
        fwrite($fh, $content);
        rewind($fh);
        try {
            return self::parse_from_handle($fh);
        } finally {
            fclose($fh);
        }
    }

    /**
     * Parse CSV from an open readable handle (e.g. {@see \stored_file::get_content_file_handle()}).
     *
     * @param resource $fh
     * @return array<int, array<string, string>> List of associative rows
     */
    public static function parse_from_handle($fh): array {
        $header = fgetcsv($fh);
        if ($header === false) {
            return [];
        }
        $header = array_map(function ($h) {
            $h = trim((string) $h);
            // Strip UTF-8 BOM often added by Excel on first column.
            if (strncmp($h, "\xEF\xBB\xBF", 3) === 0) {
                $h = substr($h, 3);
            }
            $h = preg_replace('/^\x{FEFF}/u', '', $h);
            return strtolower(trim($h));
        }, $header);
        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if (count(array_filter($line, 'strlen')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            }
            $row = self::normalize_course_id_column($row);
            $rows[] = self::normalize_name_columns($row);
        }
        return $rows;
    }

    /**
     * Copy the first matching course/target id value to the canonical key "course id" (Day 3 column mapping).
     *
     * @param array<string, string> $row
     * @return array<string, string>
     */
    public static function normalize_course_id_column(array $row): array {
        $aliases = [
            'course id', 'courseid', 'course_id', 'target id', 'target_id', 'target course id',
            'moodle course id', 'moodle id', 'courseidnumber',
        ];
        foreach ($aliases as $a) {
            if (!empty($row[$a]) && is_numeric($row[$a])) {
                $row['course id'] = trim((string) $row[$a]);
                return $row;
            }
        }
        if (!empty($row['id']) && is_numeric($row['id'])) {
            $row['course id'] = trim((string) $row['id']);
        }
        return $row;
    }

    /**
     * Map common export headers to canonical keys expected by {@see module_pair_resolver} (Day 3 flexible columns).
     *
     * @param array<string, string> $row
     * @return array<string, string>
     */
    public static function normalize_name_columns(array $row): array {
        $flatmap = [];
        foreach ($row as $k => $v) {
            $flat = preg_replace('/[^a-z0-9]/', '', strtolower((string) $k));
            if ($flat !== '') {
                $flatmap[$flat] = $v;
            }
        }

        $fullflats = [
            'fullname', 'fullnamecourse', 'coursefullname', 'coursename', 'longname', 'modulename',
            'coursetitle', 'title', 'name', 'course', 'displayname',
        ];
        $shortflats = [
            'shortname', 'courseshortname', 'coursecode', 'modulecode', 'code',
            'shortcode', 'moduleshortname',
        ];

        if (!self::nonempty($row['full name'] ?? '')) {
            foreach ($fullflats as $ff) {
                if (isset($flatmap[$ff]) && self::nonempty((string) $flatmap[$ff])) {
                    $row['full name'] = trim((string) $flatmap[$ff]);
                    break;
                }
            }
        }
        if (!self::nonempty($row['short name'] ?? '')) {
            foreach ($shortflats as $sf) {
                if (isset($flatmap[$sf]) && self::nonempty((string) $flatmap[$sf])) {
                    $row['short name'] = trim((string) $flatmap[$sf]);
                    break;
                }
            }
        }

        $idflats = [
            'idnumber', 'targetidentifier', 'targetidnumber', 'externalid', 'organisationid', 'organizationid',
        ];
        if (!self::nonempty($row['id number'] ?? '')) {
            foreach ($idflats as $idf) {
                if (isset($flatmap[$idf]) && self::nonempty((string) $flatmap[$idf])) {
                    $row['id number'] = trim((string) $flatmap[$idf]);
                    break;
                }
            }
        }

        $divflats = ['divisioncode', 'division'];
        if (!self::nonempty($row['division code'] ?? '')) {
            foreach ($divflats as $df) {
                if (isset($flatmap[$df]) && self::nonempty((string) $flatmap[$df])) {
                    $row['division code'] = trim((string) $flatmap[$df]);
                    break;
                }
            }
        }

        return $row;
    }

    /**
     * @param string $s
     * @return bool
     */
    protected static function nonempty(string $s): bool {
        return trim($s) !== '';
    }
}
