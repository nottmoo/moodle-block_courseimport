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

use core\url;
use local_uonlib\course_utils;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve CSV rows to source/target pairs: target by id → shortname → fullname → idnumber; source by year fallbacks + search.
 * If there is no target but a prior-year source matches the CSV (rolled-back short/full name), the pair may set
 * pending_create when bulk new-course category is configured in plugin settings.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module_pair_resolver {
    /**
     * Resolves one CSV data row (same logic as one iteration of {@see self::resolve()}).
     *
     * @param int $index 0-based index among non-empty data rows (matches keys used by {@see self::resolve()}).
     * @param array<string, string> $row
     * @return array{pair: ?array<string, mixed>, error: ?array<string, mixed>} Exactly one of pair or error is non-null.
     */
    public static function resolve_row(int $index, array $row): array {
        $fullname = csv_parser::cell($row, csv_parser::HEADER_FULL_NAME);
        $shortname = csv_parser::cell($row, csv_parser::HEADER_SHORT_NAME);
        $idnumber = csv_parser::cell_first(
            $row,
            csv_parser::HEADER_ID_NUMBER,
            csv_parser::HEADER_ID_NUMBER_ALT
        );

        $target = self::find_target_course($row, $shortname, $fullname, $idnumber);
        if ($target) {
            $source = self::resolve_source_with_fallbacks($target, $shortname, $fullname);
            if (!$source) {
                return [
                    'pair' => null,
                    'error' => [
                        'row' => $index + 1,
                        'error' => get_string('bulkerrorsourcenotfound', 'block_courseimport'),
                        'target_id' => (int) $target->id,
                    ],
                ];
            }

            return [
                'pair' => [
                    'target_id' => (int) $target->id,
                    'source_id' => (int) $source->id,
                    'csv_fullname' => $fullname,
                    'csv_shortname' => $shortname,
                    'csv_idnumber' => $idnumber,
                ],
                'error' => null,
            ];
        }

        // No target: try prior-year course from CSV labels; create empty target on confirm if category is set.
        $source = self::find_prior_year_source_from_csv_strings($shortname, $fullname);
        if (!$source) {
            return [
                'pair' => null,
                'error' => [
                    'row' => $index + 1,
                    'error' => get_string('bulkerrortargetnotfound', 'block_courseimport', $fullname),
                ],
            ];
        }

        if ($shortname === '' || $fullname === '') {
            return [
                'pair' => null,
                'error' => [
                    'row' => $index + 1,
                    'error' => get_string('bulkinvalidcreaterow', 'block_courseimport'),
                ],
            ];
        }

        return [
            'pair' => [
                'target_id' => 0,
                'source_id' => (int) $source->id,
                'csv_fullname' => $fullname,
                'csv_shortname' => $shortname,
                'csv_idnumber' => $idnumber,
                'pending_create' => true,
            ],
            'error' => null,
        ];
    }

    /**
     * Resolves every CSV data row to either a source/target pair or an error entry.
     *
     * Array keys are preserved as row indices (same as {@see self::resolve_row()}).
     *
     * @param array<int, array<string, string>> $rows Parsed CSV rows keyed by 0-based row index.
     * @return array{
     *     resolved: array<int, array<string, mixed>>,
     *     errors: array<int, array<string, mixed>>
     * } Maps keyed by the same indices as the input rows: resolved pairs and per-row errors.
     */
    public static function resolve(array $rows): array {
        $resolved = [];
        $errors = [];
        foreach ($rows as $i => $row) {
            $r = self::resolve_row((int) $i, $row);
            if ($r['pair'] !== null) {
                $resolved[$i] = $r['pair'];
            }
            if ($r['error'] !== null) {
                $errors[$i] = $r['error'];
            }
        }
        return ['resolved' => $resolved, 'errors' => $errors];
    }

    /**
     * Target lookup: course id → shortname → fullname → idnumber.
     */
    protected static function find_target_course(
        array $row,
        string $shortname,
        string $fullname,
        string $idnumber
    ): ?\stdClass {
        global $DB;

        $tid = self::row_target_id($row);
        if ($tid > 0) {
            try {
                return get_course($tid);
            } catch (\Throwable $e) {
                return null;
            }
        }

        if ($shortname !== '') {
            $c = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING);
            if ($c) {
                return $c;
            }
            $c = $DB->get_record_sql(
                'SELECT * FROM {course} WHERE ' . $DB->sql_equal('LOWER(shortname)', ':sn', false, true) . ' AND id <> :siteid',
                ['sn' => \core_text::strtolower($shortname), 'siteid' => SITEID],
                IGNORE_MULTIPLE
            );
            if ($c) {
                return $c;
            }
        }

        if ($fullname !== '') {
            $recs = $DB->get_records_select(
                'course',
                $DB->sql_equal('LOWER(fullname)', ':fn', false, true) . ' AND id <> :siteid',
                ['fn' => \core_text::strtolower($fullname), 'siteid' => SITEID],
                'id ASC',
                '*',
                0,
                1
            );
            if ($recs) {
                return reset($recs);
            }
        }

        if ($idnumber !== '') {
            $c = $DB->get_record('course', ['idnumber' => $idnumber], '*', IGNORE_MISSING);
            if ($c) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Find last year's course using rolled-back CSV short name and full name (no target row required).
     */
    protected static function find_prior_year_source_from_csv_strings(string $csvshort, string $csvfull): ?\stdClass {
        if ($csvshort !== '') {
            $c = self::find_course_by_rolled_back_shortname($csvshort);
            if ($c) {
                return $c;
            }
        }
        if ($csvfull !== '') {
            $c = self::find_course_by_rolled_back_fullname($csvfull);
            if ($c) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Source: previous-year shortname → previous-year fullname → module shortname search.
     */
    protected static function resolve_source_with_fallbacks(
        \stdClass $target,
        string $csvshort,
        string $csvfull
    ): ?\stdClass {
        $snbase = $csvshort !== '' ? $csvshort : $target->shortname;
        $c = self::find_course_by_rolled_back_shortname($snbase);
        if ($c) {
            return $c;
        }

        $fnbase = $csvfull !== '' ? $csvfull : $target->fullname;
        $c = self::find_course_by_rolled_back_fullname($fnbase);
        if ($c) {
            return $c;
        }

        return self::resolve_source_by_module_search($target, $csvshort);
    }

    /**
     * Roll back one year in a shortname and look up the course — exact match first, then case-insensitive.
     */
    private static function find_course_by_rolled_back_shortname(string $sn): ?\stdClass {
        global $DB;
        $prev = self::academic_year_rollback_string($sn);
        if ($prev === null || $prev === $sn) {
            return null;
        }
        $c = $DB->get_record('course', ['shortname' => $prev], '*', IGNORE_MISSING);
        if ($c) {
            return $c;
        }
        return $DB->get_record_sql(
            'SELECT * FROM {course} WHERE ' . $DB->sql_equal('LOWER(shortname)', ':sn', false, true) . ' AND id <> :siteid',
            ['sn' => \core_text::strtolower($prev), 'siteid' => SITEID],
            IGNORE_MULTIPLE
        ) ?: null;
    }

    /**
     * Roll back one year in a fullname and look up the course (case-insensitive, oldest-first).
     */
    private static function find_course_by_rolled_back_fullname(string $fn): ?\stdClass {
        global $DB;
        $prev = self::academic_year_rollback_string($fn);
        if ($prev === null || $prev === $fn) {
            return null;
        }
        $recs = $DB->get_records_select(
            'course',
            $DB->sql_equal('LOWER(fullname)', ':fn', false, true) . ' AND id <> :siteid',
            ['fn' => \core_text::strtolower($prev), 'siteid' => SITEID],
            'id ASC',
            '*',
            0,
            1
        );
        return $recs ? reset($recs) : null;
    }

    /**
     * Roll back one academic year in labels: 26-27 → 25-26, trailing 2627 → 2526.
     */
    protected static function academic_year_rollback_string(string $s): ?string {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (preg_match('/(\d{2})\s*-\s*(\d{2})/', $s, $m)) {
            $a = (int) $m[1] - 1;
            $b = (int) $m[2] - 1;
            if ($a >= 0 && $b >= 0) {
                return preg_replace('/(\d{2})\s*-\s*(\d{2})/', sprintf('%02d-%02d', $a, $b), $s, 1);
            }
        }
        if (preg_match('/(\d{2})(\d{2})(?=\D*$)/', $s, $m, PREG_OFFSET_CAPTURE)) {
            $a = (int) $m[1][0] - 1;
            $b = (int) $m[2][0] - 1;
            if ($a >= 0 && $b >= 0) {
                $old = $m[0][0];
                $pos = (int) $m[0][1];
                $new = sprintf('%02d%02d', $a, $b);
                return substr($s, 0, $pos) . $new . substr($s, $pos + strlen($old));
            }
        }
        return null;
    }

    /**
     * Broader fallback: same module code as target, exclude target shortname, pick best prior year.
     */
    protected static function resolve_source_by_module_search(\stdClass $target, string $csvshort): ?\stdClass {
        $details = course_utils::get_module_details($target);
        if ($details === false && $csvshort !== '') {
            $details = course_utils::get_module_details((object) ['shortname' => $csvshort]);
        }
        if ($details === false) {
            return null;
        }
        $modulecode = $details['modulecode'] ?? '';
        if ($modulecode === '') {
            return null;
        }
        $dummyurl = new url('/blocks/courseimport/import.php', ['id' => (int) $target->id]);
        $search = new search(['url' => $dummyurl], (int) $target->id);
        $candidates = $search->get_shortnameresults($modulecode, \core_text::strtolower($target->shortname));
        return self::pick_source_course($candidates, $details['yearcode'] ?? null);
    }

    /**
     * Moodle internal course id when the CSV has a Course id column.
     *
     * @param array<string, string> $row
     */
    protected static function row_target_id(array $row): int {
        $raw = csv_parser::cell($row, 'course id');
        if ($raw !== '' && is_numeric($raw)) {
            return (int) $raw;
        }
        return 0;
    }

    /**
     * Chooses the best prior-year source course from module-code search candidates.
     *
     * Candidates whose academic year code is present and {@see $targetyear} is set are skipped when
     * their year is greater than or equal to the target’s year (avoid same-year / newer intake).
     * Among the remainder, the course with the highest year code wins; if none qualify, the first
     * candidate is returned.
     *
     * @param array<int, \stdClass> $candidates Candidate courses from {@see search::get_shortnameresults()}.
     * @param string|null $targetyear Target course module year code from {@see course_utils::get_module_details()}, or null.
     * @return \stdClass|null The chosen course, or null when $candidates is empty.
     */
    protected static function pick_source_course(array $candidates, ?string $targetyear): ?\stdClass {
        if (!$candidates) {
            return null;
        }
        $best = null;
        $bestyear = -1;
        foreach ($candidates as $c) {
            $d = course_utils::get_module_details($c);
            $y = isset($d['yearcode']) ? (int) $d['yearcode'] : 0;
            if ($targetyear !== null && (int) $targetyear > 0 && $y > 0 && $y >= (int) $targetyear) {
                continue;
            }
            if ($y > $bestyear) {
                $bestyear = $y;
                $best = $c;
            }
        }
        return $best ?? reset($candidates);
    }
}
