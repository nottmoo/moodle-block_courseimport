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
     * Bulk-import header labels (first CSV row), after {@see normalize_header_cell()}.
     *
     * Typical files use three columns: full name, short name, id number (idnumber field).
     * You may also include a column titled Course id for Moodle’s numeric course id (normalised key “course id”).
     */
    public const HEADER_FULL_NAME = 'full name';
    public const HEADER_SHORT_NAME = 'short name';
    /** Moodle course idnumber value (same semantic as “course id number” in spreadsheets). */
    public const HEADER_ID_NUMBER = 'id number';
    /** Synonym heading only; normalises from "Course ID number". */
    public const HEADER_ID_NUMBER_ALT = 'course id number';

    /**
     * Parses CSV rows from a file path.
     *
     * @param string $path Absolute filesystem path to a CSV file.
     * @return array<int, array<string, string>> List of associative rows.
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
     * Parse CSV from raw file contents (e.g. {@see \moodleform::get_file_content()}).
     *
     * Uses php://temp so large inputs may spill to disk (PHP default behaviour).
     *
     * @param string $content
     * @return array<int, array<string, string>> List of associative rows
     */
    public static function parse_string(string $content): array {
        if ($content === '') {
            return [];
        }
        // php://temp spills to disk when large; avoids holding the whole parsed row list twice for big files.
        $fh = fopen('php://temp', 'r+b');
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
     * Reads header + data rows from a handle and invokes the callback once per non-empty data row.
     *
     * Does not build an array of all rows in memory (unlike {@see self::parse_from_handle()}).
     * The handle must be positioned at the start of the file; the method reads until EOF.
     *
     * @param resource $fh
     * @param callable $callback function(array $row, int $rowindex): void — $rowindex is 0-based over non-empty data rows only
     * @return int Number of non-empty data rows processed
     */
    public static function iterate_associative_rows_from_handle($fh, callable $callback): int {
        $header = fgetcsv($fh);
        if ($header === false) {
            return 0;
        }
        $header = array_map([self::class, 'normalize_header_cell'], $header);
        $index = 0;
        while (($line = fgetcsv($fh)) !== false) {
            if (count(array_filter($line, 'strlen')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            }
            $callback($row, $index);
            $index++;
        }
        return $index;
    }

    /**
     * Parse CSV from an open readable handle (e.g. {@see \stored_file::get_content_file_handle()}).
     *
     * @param resource $fh
     * @return array<int, array<string, string>> List of associative rows
     */
    public static function parse_from_handle($fh): array {
        $rows = [];
        self::iterate_associative_rows_from_handle($fh, function (array $row, int $i) use (&$rows): void {
            $rows[$i] = $row;
        });
        return $rows;
    }

    /**
     * Value for one canonical header column (trimmed), or empty if missing.
     *
     * @param array<string, string> $row Associative row keyed by normalised headers from {@see self::parse_from_handle()}.
     * @param string $canonicalheader Key such as {@see self::HEADER_FULL_NAME}.
     * @return string Trimmed cell value or ''.
     */
    public static function cell(array $row, string $canonicalheader): string {
        return isset($row[$canonicalheader]) ? trim((string) $row[$canonicalheader]) : '';
    }

    /**
     * First non-empty trimmed cell among the given header keys (order = preference).
     *
     * @param array<string, string> $row Associative row keyed by normalised headers.
     * @param string ...$keys One or more canonical header keys (e.g. id number vs course id number).
     * @return string First non-empty value, or '' if all empty or keys missing.
     */
    public static function cell_first(array $row, string ...$keys): string {
        foreach ($keys as $key) {
            $v = self::cell($row, $key);
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    /**
     * Normalizes a CSV header cell key (trim, remove BOM, lowercase).
     *
     * @param mixed $headercell Raw header cell value from fgetcsv.
     * @return string Normalized header key.
     */
    protected static function normalize_header_cell($headercell): string {
        $headercell = trim((string) $headercell);
        // Strip UTF-8 BOM often added by Excel on first column.
        if (strncmp($headercell, "\xEF\xBB\xBF", 3) === 0) {
            $headercell = substr($headercell, 3);
        }
        $headercell = preg_replace('/^\x{FEFF}/u', '', $headercell);
        return strtolower(trim((string) $headercell));
    }
}
