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

use enrol_nottingham\relationship;

/**
 * Resolve CSV rows to source/target pairs: target by shortname (verified with fullname and idnumber);
 * source from the existing enrol_nottingham ancestor link only.
 * Both the source and target courses must already exist; bulk import never creates courses.
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
     * @param array{fullname: string, shortname: string, idnumber: string} $row
     *      Standard non-empty row from {@see csv_parser} (missing values are rejected by the parser).
     * @return array{pair: ?array<string, mixed>, error: ?array<string, mixed>} Exactly one of pair or error is non-null.
     */
    public static function resolve_row(int $index, array $row): array {
        $fullname = $row[csv_parser::FIELD_FULLNAME];
        $shortname = $row[csv_parser::FIELD_SHORTNAME];
        $idnumber = $row[csv_parser::FIELD_IDNUMBER];

        $targetlookup = self::find_target_course($shortname, $fullname, $idnumber);
        if ($targetlookup['error'] !== null) {
            return [
                'pair' => null,
                'error' => [
                    'row' => $index + 1,
                    'errortype' => $targetlookup['error'],
                    'params' => (object) [
                        'shortname' => $shortname,
                        'fullname' => $fullname,
                        'idnumber' => $idnumber,
                    ],
                ],
            ];
        }
        $target = $targetlookup['course'];

        $source = self::resolve_source($target);
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
     * Finds the target course by shortname, then verifies fullname and idnumber from the CSV.
     *
     * Caller must pass a parser-validated row: fullname, shortname and idnumber are all non-empty.
     *
     * @param string $shortname Target course short name from the CSV row.
     * @param string $fullname Target course full name from the CSV row.
     * @param string $idnumber Target course idnumber from the CSV row.
     * @return array{course: ?\stdClass, error: ?string} Course on success; error lang string id on failure.
     */
    protected static function find_target_course(
        string $shortname,
        string $fullname,
        string $idnumber
    ): array {
        global $DB;

        $course = $DB->get_record(
            'course',
            ['shortname' => $shortname],
            'id, fullname, shortname, idnumber',
            IGNORE_MISSING
        );
        if (!$course) {
            return ['course' => null, 'error' => 'bulkerrortargetnotfound'];
        }

        if ($course->fullname !== $fullname || (string) $course->idnumber !== $idnumber) {
            return ['course' => null, 'error' => 'bulkerrortargetmismatch'];
        }

        return ['course' => $course, 'error' => null];
    }

    /**
     * Resolves the source course from the existing enrol_nottingham ancestor link.
     *
     * Uses {@see relationship::ancestor()} only. If a prior-year course can be linked,
     * {@see relationship::ancestor_search()} will already have done so (including any
     * semester/offering relaxation). Bulk must not re-search or relax matching itself.
     *
     * @param \stdClass $target Target course already matched from the CSV.
     * @return \stdClass|null
     */
    protected static function resolve_source(\stdClass $target): ?\stdClass {
        $ancestor = relationship::ancestor((int) $target->id);
        if ($ancestor === false) {
            return null;
        }
        return $ancestor;
    }
}
