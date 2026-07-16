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

namespace block_courseimport\local;

use block_courseimport\bulk_config;
use block_courseimport\csv_parser;
use block_courseimport\module_pair_resolver;

/**
 * Server-side bulk CSV processing used by bulk/submit.php.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_submit_service {

    /**
     * Parses CSV from raw bytes (e.g. {@see \moodleform::get_file_content()}).
     *
     * @param string $csvcontent
     * @return array{pairs: array, errors: array, summary: array}
     * @throws \moodle_exception
     */
    public static function build_confirmation_payload_from_csv_string(string $csvcontent): array {
        if ($csvcontent === '') {
            throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
        }
        return self::build_confirmation_payload_from_parser(csv_parser::from_string($csvcontent));
    }

    /**
     * Builds confirmation payload by resolving each standard CSV row.
     *
     * @param csv_parser $csv Parser positioned before the first data row.
     * @return array{pairs: array, errors: array, summary: array}
     */
    private static function build_confirmation_payload_from_parser(csv_parser $csv): array {
        $resolved = [];
        $errors = [];
        $datacount = 0;
        $maxrows = bulk_config::MAX_CSV_ROWS;

        foreach ($csv as $rowindex => $row) {
            $datacount++;
            if ($datacount > $maxrows) {
                throw new \moodle_exception('bulkmaxrowsexceeded', 'block_courseimport', '', $maxrows);
            }
            $resolution = module_pair_resolver::resolve_row($rowindex, $row);
            if ($resolution['pair'] !== null) {
                $resolved[$rowindex] = $resolution['pair'];
            }
            if ($resolution['error'] !== null) {
                $errors[$rowindex] = $resolution['error'];
            }
        }

        if ($datacount === 0) {
            throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
        }

        $pairs = array_values($resolved);
        $seentargets = [];
        foreach ($pairs as $pair) {
            $targetid = (int) ($pair['target_id'] ?? 0);
            if ($targetid > 0 && isset($seentargets[$targetid])) {
                throw new \moodle_exception('bulkduplicatetargets', 'block_courseimport');
            }
            if ($targetid > 0) {
                $seentargets[$targetid] = true;
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
            return self::build_confirmation_payload_from_parser(csv_parser::from_handle($handle));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Formats a preview error row using its lang string identifier and parameters.
     *
     * @param array<string, mixed> $errorrow Keys: errortype (string), optional params (object|array).
     * @return string
     */
    public static function format_preview_error(array $errorrow): string {
        $errortype = (string) ($errorrow['errortype'] ?? 'bulkunknownerror');
        if (!empty($errorrow['params'])) {
            return get_string($errortype, 'block_courseimport', $errorrow['params']);
        }
        return get_string($errortype, 'block_courseimport');
    }
}
