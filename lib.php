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

/**
 * Library functions for the block.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add an import link into the course navigation.
 *
 * @param \navigation_node $parentnode
 * @param \stdClass $course
 * @param \context_course $context
 * @return void
 */
function block_courseimport_extend_navigation_course(
    navigation_node $parentnode,
    stdClass $course,
    context_course $context
) {
    $requiredcapabilities = [
        'block/courseimport:view',
        'moodle/course:update',
    ];
    if (has_all_capabilities($requiredcapabilities, $context)) {
        // Add a link to the import page.
        $url = new moodle_url('/blocks/courseimport/import.php', ['id' => $course->id]);
        $text = get_string('importlink', 'block_courseimport');
        $node = $parentnode->add($text, $url);
        $node->set_force_into_more_menu(true);
    }
}
