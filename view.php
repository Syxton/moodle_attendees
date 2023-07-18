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
 * Attendees module version information
 *
 * @package mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/attendees/lib.php');
require_once($CFG->libdir.'/completionlib.php');

$id      = optional_param('id', 0, PARAM_INT); // Course Module ID
$p       = optional_param('p', 0, PARAM_INT);  // Attendees instance ID
$tab     = optional_param('tab', null, PARAM_ALPHANUM);

if ($p) {
    if (!$attendees = $DB->get_record('attendees', array('id'=>$p))) {
        throw new \moodle_exception('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('attendees', $attendees->id, $attendees->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('attendees', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    $attendees = $DB->get_record('attendees', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendees:view', $context);

// Completion and trigger events.
attendees_view($attendees, $course, $cm, $context);

$PAGE->set_url('/mod/attendees/view.php', array('id' => $cm->id));

$options = empty($attendees->displayoptions) ? [] : (array) unserialize_array($attendees->displayoptions);

$activityheader = ['hidecompletion' => false];
if (empty($options['printintro'])) {
    $activityheader['description'] = '';
}

$PAGE->set_title($course->shortname.': '.$attendees->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($attendees);

if ($attendees->kioskmode) {
    $PAGE->add_body_class('limitedwidth');
    
}

if (!$PAGE->activityheader->is_title_allowed()) {
    $activityheader['title'] = "";
}

$PAGE->activityheader->set_attrs($activityheader);
echo $OUTPUT->header();

$tab = !$tab ? $attendees->defaultview : $tab;

$content = attendees_get_ui($cm, $attendees, $course, $tab);

$formatoptions = new stdClass;
$formatoptions->noclean = true;
$formatoptions->overflowdiv = true;
$formatoptions->context = $context;
$content = format_text($content, $attendees->contentformat, $formatoptions);
echo $OUTPUT->box($content, "generalbox center clearfix");
echo $OUTPUT->footer();
