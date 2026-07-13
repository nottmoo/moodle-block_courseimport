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

/**
 * Resolve CSV rows to source/target pairs: target by id → shortname → fullname → idnumber; source by prior-year shortname + search.
 * Rows without a CSV shortname do not use fullname to resolve the target (UoN courses use the naming convention on shortname).
 * If there is no target but a prior-year source matches via rolled-back shortname, the pair may set pending_create when
 * bulk new-course category is configured in plugin settings.
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
     * @param array{fullname: string, shortname: string, idnumber: string} $row Standard row from {@see csv_parser}.
     * @return array{pair: ?array<string, mixed>, error: ?array<string, mixed>} Exactly one of pair or error is non-null.
     */
    public static function resolve_row(int $index, array $row): array {
        $fullname = $row[csv_parser::FIELD_FULLNAME];
        $shortname = $row[csv_parser::FIELD_SHORTNAME];
        $idnumber = $row[csv_parser::FIELD_IDNUMBER];

        $target = self::find_target_course($shortname, $fullname, $idnumber);
        if ($target) {
            $source = self::resolve_source_with_fallbacks($target, $shortname);
            if (!$source) {
                return [
                    'pair' => null,
                    'error' => [
                        'row' => $index + 1,
                        'errortype' => 'bulkerrorsourcenotfound',
                        'params' => (object) ['targetid' => (int) $target->id],
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

        // No target: try prior-year course from CSV shortname only; create empty target on confirm if category is set.
        $source = self::find_prior_year_source_from_csv_strings($shortname);
        if (!$source) {
            return [
                'pair' => null,
                'error' => [
                    'row' => $index + 1,
                    'errortype' => 'bulkerrortargetnotfound',
                    'params' => (object) ['fullname' => $fullname],
                ],
            ];
        }

        if ($shortname === '' || $fullname === '') {
            return [
                'pair' => null,
                'error' => [
                    'row' => $index + 1,
                    'errortype' => 'bulkinvalidcreaterow',
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
     * @param array<int, array{fullname: string, shortname: string, idnumber: string}> $rows Parsed CSV rows keyed by 0-based row index.
     * @return array{
     *     resolved: array<int, array<string, mixed>>,
     *     errors: array<int, array<string, mixed>>
     * } Maps keyed by the same indices as the input rows: resolved pairs and per-row errors.
     */
    public static function resolve(array $rows): array {
        $resolved = [];
        $errors = [];
        foreach ($rows as $rowindex => $row) {
            $resolution = self::resolve_row((int) $rowindex, $row);
            if ($resolution['pair'] !== null) {
                $resolved[$rowindex] = $resolution['pair'];
            }
            if ($resolution['error'] !== null) {
                $errors[$rowindex] = $resolution['error'];
            }
        }
        return ['resolved' => $resolved, 'errors' => $errors];
    }

    /**
     * Resolves the target course for a CSV row.
     *
     * Order: shortname (exact, then case-insensitive) → fullname → idnumber.
     *
     * @param string $shortname Target course short name from the CSV row.
     * @param string $fullname Target course full name from the CSV row.
     * @param string $idnumber Target course idnumber from the CSV row.
     * @return \stdClass|null Course record, or null if none matched.
     */
    protected static function find_target_course(
        string $shortname,
        string $fullname,
        string $idnumber
    ): ?\stdClass {
        global $DB;

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
     * Finds a prior-year source course when no target exists, using CSV shortname only.
     *
     * @param string $csvshort Current-year shortname from the CSV (convention-backed).
     * @return \stdClass|null Prior-year course, or null if shortname empty or no unique match.
     */
    protected static function find_prior_year_source_from_csv_strings(string $csvshort): ?\stdClass {
        if ($csvshort === '') {
            return null;
        }
        return self::find_course_by_rolled_back_shortname($csvshort);
    }

    /**
     * Source: previous-year shortname → module shortname search.
     */
    protected static function resolve_source_with_fallbacks(
        \stdClass $target,
        string $csvshort
    ): ?\stdClass {
        $snbase = $csvshort !== '' ? $csvshort : $target->shortname;
        $c = self::find_course_by_rolled_back_shortname($snbase, (int) $target->id);
        if ($c) {
            return $c;
        }

        return self::resolve_source_by_module_search($target, $csvshort);
    }

    /**
     * Looks up a course one academic year earlier than the given shortname.
     *
     * Same rules as {@see \enrol_nottingham\relationship::ancestor_search()}: exact prior-year code,
     * case-insensitive match, then {@see course_utils::shortname_match_sql()} with semester/offering relaxed.
     *
     * @param string $shortname Shortname for the newer intake (parsed by {@see course_utils::change_year()}).
     * @param int|null $excludecourseid When set, omit this course id from matches (e.g. the target course).
     * @return \stdClass|null Matching course, or null if no year in shortname, no match, or multiple ambiguous matches.
     */
    private static function find_course_by_rolled_back_shortname(string $shortname, ?int $excludecourseid = null): ?\stdClass {
        global $DB;

        $previousshortname = course_utils::change_year($shortname, -1);
        if ($previousshortname === null) {
            return null;
        }

        $select = 'shortname = :sn AND id <> :siteid';
        $params = ['sn' => $previousshortname, 'siteid' => SITEID];
        if ($excludecourseid !== null && $excludecourseid > 0) {
            $select .= ' AND id <> :excludeid';
            $params['excludeid'] = $excludecourseid;
        }
        $c = $DB->get_record_select('course', $select, $params, '*', IGNORE_MULTIPLE);
        if ($c) {
            return $c;
        }

        $selectlower = $DB->sql_equal('LOWER(shortname)', ':snlower', false, true) . ' AND id <> :siteid';
        $paramslower = [
            'snlower' => \core_text::strtolower($previousshortname),
            'siteid' => SITEID,
        ];
        if ($excludecourseid !== null && $excludecourseid > 0) {
            $selectlower .= ' AND id <> :excludeid';
            $paramslower['excludeid'] = $excludecourseid;
        }
        $c = $DB->get_record_select('course', $selectlower, $paramslower, '*', IGNORE_MULTIPLE);
        if ($c) {
            return $c;
        }

        list($similarlike, $similarparams) = course_utils::shortname_match_sql(
            $previousshortname,
            '',
            true,
            false,
            true,
            false
        );
        $similarparams['siteid'] = SITEID;
        $conds = ['id <> :siteid', $similarlike];
        if ($excludecourseid !== null && $excludecourseid > 0) {
            $similarparams['excludeid'] = $excludecourseid;
            $conds[] = 'id <> :excludeid';
        }
        $sql = 'SELECT * FROM {course} WHERE ' . implode(' AND ', $conds);
        $similarversions = $DB->get_records_sql($sql, $similarparams);
        if (!$similarversions || count($similarversions) > 1) {
            return null;
        }

        return reset($similarversions);
    }

    /**
     * Fallback source resolution: search by module code in shortname, then pick best prior year.
     *
     * @param \stdClass $target Target course (for module details and search context).
     * @param string $csvshort Optional CSV shortname to parse module details when the target record is insufficient.
     * @return \stdClass|null A candidate source course, or null.
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
