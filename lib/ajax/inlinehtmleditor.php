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
 * Provide interface for blocks AJAX actions
 *
 * @copyright  2011 Lancaster University Network Services Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package core
 */

define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once("$CFG->libdir/ajax/inlinehtmleditor_form.php");

// Initialise ALL common incoming parameters here, up front.
//require_login($courseid, false, $cm);
require_sesskey();

// Set context from ID, so we don't have to guess it from other info.
$contextid = required_param('contextid', PARAM_INT);
$PAGE->set_context(context::instance_by_id($contextid));

$result = array('text'=>'POTATOES');

echo json_encode($result);

