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

defined('MOODLE_INTERNAL') || die();

/**
 * Server-side bulk CSV processing used by bulk/submit.php.
 */
final class bulk_submit_service {

    /**
     * Parses CSV from raw bytes (e.g. {@see \moodleform::get_file_content()}); no temp file on disk.
     *
     * @param string $csvcontent
     * @return array{pairs: array, errors: array, summary: array}
     * @throws \moodle_exception
     */
    public static function build_confirmation_payload_from_csv_string(string $csvcontent): array {
        $rows = csv_parser::parse_string($csvcontent);
        return self::build_confirmation_payload_from_rows($rows);
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return array{pairs: array, errors: array, summary: array}
     */
    private static function build_confirmation_payload_from_rows(array $rows): array {
        if ($rows === []) {
            throw new \moodle_exception('bulkcsvrequired', 'block_courseimport');
        }

        $maxrows = bulk_config::max_csv_rows();
        if (count($rows) > $maxrows) {
            throw new \moodle_exception('bulkmaxrowsexceeded', 'block_courseimport', '', $maxrows);
        }

        $resolution = module_pair_resolver::resolve($rows);
        $pairs = array_values($resolution['resolved']);
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
            'errors' => array_values($resolution['errors']),
            'summary' => [
                'rows' => count($rows),
                'resolved' => count($pairs),
                'unmatched' => count($resolution['errors']),
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
            $rows = csv_parser::parse_from_handle($handle);
            return self::build_confirmation_payload_from_rows($rows);
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
