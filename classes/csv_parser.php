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

/**
 * Iterator over bulk rollover CSV rows in a fixed three-column shape.
 *
 * Required columns (any order, recognised synonyms): full name, short name, id number.
 * Each iterated row is {@see FIELD_FULLNAME}, {@see FIELD_SHORTNAME}, {@see FIELD_IDNUMBER}
 * only; extra CSV columns are ignored.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class csv_parser implements \Iterator {
    /** @var string Standard row key for course full name. */
    public const FIELD_FULLNAME = 'fullname';

    /** @var string Standard row key for course short name. */
    public const FIELD_SHORTNAME = 'shortname';

    /** @var string Standard row key for course idnumber. */
    public const FIELD_IDNUMBER = 'idnumber';

    /**
     * Normalised header synonyms mapped to each standard field.
     *
     * @var array<string, list<string>>
     */
    private const HEADER_SYNONYMS = [
        self::FIELD_FULLNAME => ['full name', 'course full name', 'fullname'],
        self::FIELD_SHORTNAME => ['short name', 'course short name', 'shortname'],
        self::FIELD_IDNUMBER => ['id number', 'course id number', 'idnumber'],
    ];

    /** @var resource */
    private $handle;

    /** @var bool Whether this instance should fclose the handle on destruction. */
    private bool $closehandle;

    /** @var array<string, int> Maps each standard field to its CSV column index. */
    private array $fieldcolumns = [];

    /** @var int|null File offset immediately after the header row (for rewind). */
    private ?int $headeroffset = null;

    /** @var int 0-based index among non-empty data rows. */
    private int $rowindex = -1;

    /**
     * @var array{fullname: string, shortname: string, idnumber: string}|null Current row.
     */
    private ?array $current = null;

    /** @var bool Whether {@see current()} is valid. */
    private bool $iteratorvalid = false;

    /**
     * @param resource $handle Readable CSV handle positioned at the start of the file.
     * @param bool $closehandle When true, the handle is closed on destruction.
     */
    private function __construct($handle, bool $closehandle) {
        $this->handle = $handle;
        $this->closehandle = $closehandle;
        $this->read_header();
        $this->next();
    }

    /**
     * Opens a parser from full CSV file contents (in-memory stream; no local temp files).
     *
     * @param string $content
     * @return self
     * @throws \moodle_exception
     */
    public static function from_string(string $content): self {
        $handle = fopen('data://text/plain;base64,' . base64_encode($content), 'rb');
        if ($handle === false) {
            throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
        }
        return new self($handle, true);
    }

    /**
     * Opens a parser from a readable handle (caller may close unless this instance owns it).
     *
     * @param resource $handle
     * @return self
     * @throws \moodle_exception
     */
    public static function from_handle($handle): self {
        return new self($handle, false);
    }

    /**
     * Closes the handle when this instance owns it.
     */
    public function __destruct() {
        if ($this->closehandle && is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * @return array{fullname: string, shortname: string, idnumber: string}
     */
    #[\Override]
    public function current(): array {
        return $this->current ?? [
            self::FIELD_FULLNAME => '',
            self::FIELD_SHORTNAME => '',
            self::FIELD_IDNUMBER => '',
        ];
    }

    #[\Override]
    public function key(): int {
        return $this->rowindex;
    }

    #[\Override]
    public function next(): void {
        $this->current = null;
        $this->iteratorvalid = false;

        while (($line = fgetcsv($this->handle)) !== false) {
            if ($this->is_empty_line($line)) {
                continue;
            }
            $this->rowindex++;
            $this->current = $this->build_standard_row($line);
            $this->iteratorvalid = true;
            return;
        }
    }

    #[\Override]
    public function rewind(): void {
        if ($this->headeroffset === null) {
            return;
        }
        fseek($this->handle, $this->headeroffset);
        $this->rowindex = -1;
        $this->current = null;
        $this->iteratorvalid = false;
        $this->next();
    }

    #[\Override]
    public function valid(): bool {
        return $this->iteratorvalid;
    }

    /**
     * Reads and validates the header row, mapping required columns.
     *
     * @return void
     * @throws \moodle_exception
     */
    private function read_header(): void {
        $header = fgetcsv($this->handle);
        if ($header === false || $this->is_empty_line($header)) {
            throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
        }

        $normalized = array_map([self::class, 'normalize_header_cell'], $header);
        $this->fieldcolumns = $this->map_required_columns($normalized);
        $offset = ftell($this->handle);
        $this->headeroffset = $offset === false ? null : $offset;
    }

    /**
     * @param list<string> $normalizedheaders
     * @return array<string, int>
     * @throws \moodle_exception
     */
    private function map_required_columns(array $normalizedheaders): array {
        $columns = [];
        foreach (self::HEADER_SYNONYMS as $field => $synonyms) {
            $columnindex = null;
            foreach ($normalizedheaders as $index => $header) {
                if (in_array($header, $synonyms, true)) {
                    $columnindex = $index;
                    break;
                }
            }
            if ($columnindex === null) {
                throw new \moodle_exception('bulkcsvinvalidheaders', 'block_courseimport');
            }
            $columns[$field] = $columnindex;
        }
        return $columns;
    }

    /**
     * @param list<string|null> $line
     * @return array{fullname: string, shortname: string, idnumber: string}
     * @throws \moodle_exception
     */
    private function build_standard_row(array $line): array {
        $row = [
            self::FIELD_FULLNAME => trim((string) ($line[$this->fieldcolumns[self::FIELD_FULLNAME]] ?? '')),
            self::FIELD_SHORTNAME => trim((string) ($line[$this->fieldcolumns[self::FIELD_SHORTNAME]] ?? '')),
            self::FIELD_IDNUMBER => trim((string) ($line[$this->fieldcolumns[self::FIELD_IDNUMBER]] ?? '')),
        ];

        foreach ($row as $value) {
            if ($value === '') {
                throw new \moodle_exception('bulkcsvinvalidrow', 'block_courseimport', '', $this->rowindex + 1);
            }
        }

        return $row;
    }

    /**
     * @param list<string|null> $line
     * @return bool
     */
    private function is_empty_line(array $line): bool {
        return count(array_filter($line, static fn($cell) => trim((string) $cell) !== '')) === 0;
    }

    /**
     * @param mixed $headercell
     * @return string
     */
    private static function normalize_header_cell($headercell): string {
        $headercell = trim((string) $headercell);
        if (strncmp($headercell, "\xEF\xBB\xBF", 3) === 0) {
            $headercell = substr($headercell, 3);
        }
        $headercell = preg_replace('/^\x{FEFF}/u', '', $headercell);
        return strtolower(trim((string) $headercell));
    }
}
