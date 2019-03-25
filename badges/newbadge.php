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
 * First step page for creating a new badge
 *
 * @package    core
 * @subpackage badges
 * @copyright  2012 onwards Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/badges/edit_form.php');

$type = required_param('type', PARAM_INT);
$courseid = optional_param('id', 0, PARAM_INT);

require_login();

if (empty($CFG->enablebadges)) {
    print_error('badgesdisabled', 'badges');
}

if (empty($CFG->badges_allowcoursebadges) && ($type == BADGE_TYPE_COURSE)) {
    print_error('coursebadgesdisabled', 'badges');
}

$title = get_string('create', 'badges');

if (($type == BADGE_TYPE_COURSE) && ($course = $DB->get_record('course', array('id' => $courseid)))) {
    require_login($course);
    $coursecontext = context_course::instance($course->id);
    $PAGE->set_context($coursecontext);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_url('/badges/newbadge.php', array('type' => $type, 'id' => $course->id));
    $heading = format_string($course->fullname, true, array('context' => $coursecontext)) . ": " . $title;
    $PAGE->set_heading($heading);
    $PAGE->set_title($heading);
} else {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_pagelayout('admin');
    $PAGE->set_url('/badges/newbadge.php', array('type' => $type));
    $PAGE->set_heading($title);
    $PAGE->set_title($title);
}

require_capability('moodle/badges:createbadge', $PAGE->context);

$PAGE->requires->js('/badges/backpack.js');
$PAGE->requires->js_init_call('check_site_access', null, false);

$fordb = new stdClass();
$fordb->id = null;

$form = new edit_details_form($PAGE->url, array('action' => 'new'));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/badges/index.php', array('type' => $type, 'id' => $courseid)));
} else if ($data = $form->get_data()) {
    // Creating new badge here.

    // We checked permissions, and the form validated the type of the parameters so we are free to
    // create the badge in the DB.

    $expiredate = ($data->expiry == 1) ? $data->expiredate : null;
    $expireperiod = ($data->expiry == 2) ? $data->expireperiod : null;
    $badgecourseid = ($type == BADGE_TYPE_COURSE) ? $courseid : null;
    $newbadge = badge::create(
        $data->name,
        $data->description,
        $data->issuername,
        $data->issuerurl,
        $data->issuercontact,
        $data->version,
        $data->language,
        $data->imageauthorname,
        $data->imageauthoremail,
        $data->imageauthorurl,
        $data->imagecaption,
        $type,
        $expiredate,
        $expireperiod,
        $badgecourseid);

    // Trigger event, badge created.
    $eventparams = array('objectid' => $newbadge->id, 'context' => $PAGE->context);
    $event = \core\event\badge_created::create($eventparams);
    $event->trigger();

    badges_process_badge_image($newbadge, $form->save_temp_file('image'));
    // If a user can configure badge criteria, they will be redirected to the criteria page.
    if (has_capability('moodle/badges:configurecriteria', $PAGE->context)) {
        redirect(new moodle_url('/badges/criteria.php', array('id' => $newbadge->id)));
    }
    redirect(new moodle_url('/badges/overview.php', array('id' => $newbadge->id)));
}

echo $OUTPUT->header();
echo $OUTPUT->box('', 'notifyproblem hide', 'check_connection');

$form->display();

echo $OUTPUT->footer();
