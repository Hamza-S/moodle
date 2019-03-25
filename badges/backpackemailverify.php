<?php
// This file is part of Moodle - http://moodle.org/
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
 * Endpoint for the verification email link.
 *
 * @package    core
 * @subpackage badges
 * @copyright  2016 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once(__DIR__ . '/lib/backpacklib.php');

$data = optional_param('data', '', PARAM_RAW);
require_login();
$PAGE->set_url('/badges/openbackpackemailverify.php');
$PAGE->set_context(context_user::instance($USER->id));

list($url, $message, $type) = \core_badges::verify_email($data);
redirect($url, $message, null, $type);
