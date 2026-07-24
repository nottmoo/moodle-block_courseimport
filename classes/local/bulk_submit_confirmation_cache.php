<?php
// This file is part of courseimport block in Moodle - http://moodle.org/
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

use block_courseimport\job;

/**
 * Bulk CSV preview / confirm payload stored via MUC ({@see cache_store::MODE_SESSION}).
 *
 * Each upload gets a unique pack id so two browser tabs do not overwrite each other.
 * Data is split across cache entries (summary/meta + one page of pairs + one page of errors)
 * so preview requests only load the page being viewed.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_submit_confirmation_cache {

    /** Cache area name for bulk submit confirmation. */
    private const CACHE_AREA = 'bulk_submit_confirmation';

    /** Default rows per cached preview page (must match UI paging). */
    public const ROWS_PER_PAGE = 25;

    /**
     * Creates a new pack id, stores paged pairs/errors + summary, returns the pack id.
     *
     * @param array<int, array<string, mixed>> $pairs
     * @param array<int, array<string, mixed>> $errors
     * @param array $summary Keys: rows, resolved, unmatched (toimport/skipped added here).
     * @param int $rowsperpage
     * @return string Pack id for URLs / confirm.
     */
    public static function store_paged(
        array $pairs,
        array $errors,
        array $summary,
        int $rowsperpage = self::ROWS_PER_PAGE
    ): string {
        $packid = self::generate_packid();
        $pairs = array_values($pairs);
        $errors = array_values($errors);
        $rowsperpage = max(1, $rowsperpage);

        $skipcounts = self::count_skip_and_import($pairs);
        $summary = array_merge($summary, $skipcounts);

        $pairpagecount = $pairs === [] ? 0 : (int) ceil(count($pairs) / $rowsperpage);
        $errorpagecount = $errors === [] ? 0 : (int) ceil(count($errors) / $rowsperpage);

        $cache = self::cache();
        $cache->set(self::meta_key($packid), [
            'summary' => $summary,
            'pairtotal' => count($pairs),
            'errortotal' => count($errors),
            'pairpagecount' => $pairpagecount,
            'errorpagecount' => $errorpagecount,
            'rowsperpage' => $rowsperpage,
        ]);

        for ($page = 0; $page < $pairpagecount; $page++) {
            $cache->set(
                self::pairs_key($packid, $page),
                array_slice($pairs, $page * $rowsperpage, $rowsperpage)
            );
        }
        for ($page = 0; $page < $errorpagecount; $page++) {
            $cache->set(
                self::errors_key($packid, $page),
                array_slice($errors, $page * $rowsperpage, $rowsperpage)
            );
        }

        return $packid;
    }

    /**
     * Returns pack meta (summary + totals), or null if missing/invalid.
     *
     * @param string $packid
     * @return array<string, mixed>|null
     */
    public static function get_meta(string $packid): ?array {
        if (!self::is_valid_packid($packid)) {
            return null;
        }
        $data = self::cache()->get(self::meta_key($packid));
        return is_array($data) ? $data : null;
    }

    /**
     * One page of resolved pairs for preview (empty if out of range / missing).
     *
     * @param string $packid
     * @param int $page Zero-based.
     * @return array<int, array<string, mixed>>
     */
    public static function get_pairs_page(string $packid, int $page): array {
        $meta = self::get_meta($packid);
        if ($meta === null || $page < 0 || $page >= (int) ($meta['pairpagecount'] ?? 0)) {
            return [];
        }
        $data = self::cache()->get(self::pairs_key($packid, $page));
        return is_array($data) ? array_values($data) : [];
    }

    /**
     * One page of resolution errors for preview.
     *
     * @param string $packid
     * @param int $page Zero-based.
     * @return array<int, array<string, mixed>>
     */
    public static function get_errors_page(string $packid, int $page): array {
        $meta = self::get_meta($packid);
        if ($meta === null || $page < 0 || $page >= (int) ($meta['errorpagecount'] ?? 0)) {
            return [];
        }
        $data = self::cache()->get(self::errors_key($packid, $page));
        return is_array($data) ? array_values($data) : [];
    }

    /**
     * All resolved pairs (confirm only). Loads each cached page in turn.
     *
     * @param string $packid
     * @return array<int, array<string, mixed>>|null Null if pack missing.
     */
    public static function get_all_pairs(string $packid): ?array {
        $meta = self::get_meta($packid);
        if ($meta === null) {
            return null;
        }
        $pairpagecount = (int) ($meta['pairpagecount'] ?? 0);
        if ($pairpagecount < 1) {
            return [];
        }
        $all = [];
        $cache = self::cache();
        for ($page = 0; $page < $pairpagecount; $page++) {
            $chunk = $cache->get(self::pairs_key($packid, $page));
            if (!is_array($chunk)) {
                return null;
            }
            foreach ($chunk as $pair) {
                $all[] = $pair;
            }
        }
        return $all;
    }

    /**
     * Removes every cache entry for this pack id.
     *
     * @param string $packid
     */
    public static function delete_pack(string $packid): void {
        if (!self::is_valid_packid($packid)) {
            return;
        }
        $cache = self::cache();
        $meta = $cache->get(self::meta_key($packid));
        $keys = [self::meta_key($packid)];
        if (is_array($meta)) {
            $pairpagecount = (int) ($meta['pairpagecount'] ?? 0);
            $errorpagecount = (int) ($meta['errorpagecount'] ?? 0);
            for ($page = 0; $page < $pairpagecount; $page++) {
                $keys[] = self::pairs_key($packid, $page);
            }
            for ($page = 0; $page < $errorpagecount; $page++) {
                $keys[] = self::errors_key($packid, $page);
            }
        }
        $cache->delete_many($keys);
    }

    /**
     * @return string Alphanumeric pack id safe for simplekeys + PARAM_ALPHANUM.
     */
    public static function generate_packid(): string {
        return random_string(16);
    }

    /**
     * @param string $packid
     * @return bool
     */
    public static function is_valid_packid(string $packid): bool {
        return $packid !== '' && (bool) preg_match('/^[a-zA-Z0-9]+$/', $packid);
    }

    /**
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make('block_courseimport', self::CACHE_AREA);
    }

    /**
     * @param string $packid
     * @return string
     */
    private static function meta_key(string $packid): string {
        return $packid . 'meta';
    }

    /**
     * @param string $packid
     * @param int $page
     * @return string
     */
    private static function pairs_key(string $packid, int $page): string {
        return $packid . 'pairs' . $page;
    }

    /**
     * @param string $packid
     * @param int $page
     * @return string
     */
    private static function errors_key(string $packid, int $page): string {
        return $packid . 'errors' . $page;
    }

    /**
     * @param array<int, array<string, mixed>> $resolvedpairs
     * @return array{toimport: int, skipped: int}
     */
    private static function count_skip_and_import(array $resolvedpairs): array {
        $toimport = 0;
        $skipped = 0;
        foreach ($resolvedpairs as $pair) {
            $targetid = (int) ($pair['target_id'] ?? 0);
            $sourceid = (int) ($pair['source_id'] ?? 0);
            if ($targetid > 0 && $sourceid > 0 && job::bulk_skip_reason($targetid, $sourceid) !== null) {
                $skipped++;
            } else {
                $toimport++;
            }
        }
        return ['toimport' => $toimport, 'skipped' => $skipped];
    }
}
