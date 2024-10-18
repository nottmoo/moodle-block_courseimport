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
 * Job failed to process exception.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_courseimport;

/**
 * Job failed to process exception.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_failed extends \Exception {
    /** @var string The subject of an e-mail. */
    public $subject;

    /**
     * Constructor
     *
     * @param string $message A message to be sent to the admin
     * @param string|null $subject A subject for the e-mail (if the default is not enough)
     */
    public function __construct($message = "", string $subject = null) {
        if (is_null($subject)) {
            $this->subject = get_string('alertemailsubject', 'block_courseimport');
        } else {
            $this->subject = $subject;
        }
        parent::__construct($message);
    }
}
