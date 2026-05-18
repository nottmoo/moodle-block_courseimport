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

/**
 * Bulk submit flow: draft file → confirmation session payload, and confirm → submit.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_courseimport\local;

use block_courseimport\bulk_config;
use block_courseimport\bulk_submitter;
use block_courseimport\csv_parser;
use block_courseimport\module_pair_resolver;

/**
 * Server-side bulk CSV processing used by bulk/submit.php.
 */
final class bulk_submit_service {

    /**
     * Parses CSV from raw bytes (e.g. {@see \moodleform::get_file_content()}).
     *
     * Uses a stream and row-by-row parsing so parsed rows are not all held in memory at once.
     *
     * @param string $csvcontent
     * @return array{pairs: array, errors: array, summary: array}
     * @throws \moodle_exception
     */
    public static function build_confirmation_payload_from_csv_string(string $csvcontent): array {
        if ($csvcontent === '') {
            throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
        }
        $fh = fopen('php://temp', 'r+b');
        if ($fh === false) {
            throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
        }
        fwrite($fh, $csvcontent);
        rewind($fh);
        try {
            return self::build_confirmation_payload_from_handle($fh);
        } finally {
            fclose($fh);
        }
    }

    /**
     * Builds confirmation payload by streaming one CSV row at a time (bounded memory for row parsing).
     *
     * @param resource $fh Readable handle at start of CSV (caller closes).
     * @return array{pairs: array, errors: array, summary: array}
     */
    private static function build_confirmation_payload_from_handle($fh): array {
        $resolved = [];
        $errors = [];
        $datacount = 0;
        $maxrows = bulk_config::MAX_CSV_ROWS;

        $rowcallback = function (array $row, int $i) use (&$resolved, &$errors, &$datacount, $maxrows): void {
            $datacount++;
            if ($datacount > $maxrows) {
                throw new \moodle_exception('bulkmaxrowsexceeded', 'block_courseimport', '', $maxrows);
            }
            $r = module_pair_resolver::resolve_row($i, $row);
            if ($r['pair'] !== null) {
                $resolved[$i] = $r['pair'];
            }
            if ($r['error'] !== null) {
                $errors[$i] = $r['error'];
            }
        };
        csv_parser::iterate_associative_rows_from_handle($fh, $rowcallback);

        if ($datacount === 0) {
            throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
        }

        $pairs = array_values($resolved);
        $seentargets = [];
        $seencreateshortnames = [];
        foreach ($pairs as $pair) {
            $targetid = (int) ($pair['target_id'] ?? 0);
            if ($targetid > 0 && isset($seentargets[$targetid])) {
                throw new \moodle_exception('bulkduplicatetargets', 'block_courseimport');
            }
            if ($targetid > 0) {
                $seentargets[$targetid] = true;
            }
            if (!empty($pair['pending_create'])) {
                $short = \core_text::strtolower(trim((string) ($pair['csv_shortname'] ?? '')));
                if ($short !== '' && isset($seencreateshortnames[$short])) {
                    throw new \moodle_exception('bulkduplicateshortnames', 'block_courseimport');
                }
                $seencreateshortnames[$short] = true;
            }
        }

        return [
            'pairs' => $pairs,
            'errors' => array_values($errors),
            'summary' => [
                'rows' => $datacount,
                'resolved' => count($pairs),
                'unmatched' => count($errors),
            ],
        ];
    }

    /**
     * Reads a draft filepicker item by draft id (e.g. {@see optional_param()} only, without a fresh form post).
     *
     * @param int $userid Current user id (draft owner).
     * @param int $draftitemid Filepicker draft item id.
     * @return array{pairs: array, errors: array, summary: array}
     * @throws \moodle_exception
     */
    public static function build_confirmation_payload_from_draft(int $userid, int $draftitemid): array {
        $fs = get_file_storage();
        $usercontext = \context_user::instance($userid);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'itemid, filepath, filename', false);
        $file = $files ? reset($files) : null;
        if (!$file) {
            throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
        }
        $handle = $file->get_content_file_handle();
        if ($handle === false) {
            throw new \moodle_exception('bulkcsvinvalidtype', 'block_courseimport');
        }
        try {
            return self::build_confirmation_payload_from_handle($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Queues import jobs for the confirmed pair list.
     *
     * @param array<int, array<string, mixed>> $pairs
     * @param int $userid
     * @return array<string, mixed> Same shape as {@see bulk_submitter::submit()}.
     */
    public static function submit_confirmed_pairs(array $pairs, int $userid): array {
        return bulk_submitter::submit($pairs, $userid);
    }

    /**
     * Unmatched row message for preview.
     *
     * @param array<string, mixed> $errorrow
     * @return string
     */
    public static function format_preview_error(array $errorrow): string {
        $msg = (string) ($errorrow['error'] ?? get_string('bulkunknownerror', 'block_courseimport'));
        $parts = [$msg];
        foreach (['target_id', 'modulecode', 'shortname'] as $key) {
            if (!empty($errorrow[$key])) {
                $parts[] = $key . ': ' . $errorrow[$key];
            }
        }
        return implode(' — ', $parts);
    }
}
