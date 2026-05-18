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

/**
 * Bulk CSV preview / confirm payload stored via MUC ({@see cache_store::MODE_SESSION}), not $SESSION.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bulk_submit_confirmation_cache {

    /** Cache area name for bulk submit confirmation. */
    private const CACHE_AREA = 'bulk_submit_confirmation';
    /** Cache key for the stored confirmation pack. */
    private const CACHE_KEY = 'pack';

    /**
     * Returns the bulk confirmation payload from session cache.
     *
     * @return array<string, mixed>|null Payload from last preview step, or null if none.
     */
    public static function get_pack(): ?array {
        $cache = \cache::make('block_courseimport', self::CACHE_AREA);
        $data = $cache->get(self::CACHE_KEY);
        if ($data === false) {
            return null;
        }
        return is_array($data) ? $data : null;
    }

    /**
     * Stores the bulk confirmation payload in session cache.
     *
     * @param array<string, mixed> $pack Keys: pairs, errors, summary.
     */
    public static function set_pack(array $pack): void {
        $cache = \cache::make('block_courseimport', self::CACHE_AREA);
        $cache->set(self::CACHE_KEY, $pack);
    }

    /**
     * Removes the bulk confirmation payload from session cache.
     */
    public static function delete_pack(): void {
        $cache = \cache::make('block_courseimport', self::CACHE_AREA);
        $cache->delete(self::CACHE_KEY);
    }
}
