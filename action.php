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
 * @package    mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/attendees/lib.php');
require_once($CFG->libdir.'/completionlib.php');

$id      = optional_param('id', 0, PARAM_INT); // Course Module ID.
$userid  = optional_param('userid', 0, PARAM_INT); // User ID ID.
$tab     = optional_param('tab', null, PARAM_ALPHANUM);
$code    = optional_param('code', null, PARAM_RAW);

if (!$cm = get_coursemodule_from_id('attendees', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}

$attendees = $DB->get_record('attendees', array('id' => $cm->instance), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/attendees:view', $context);
// Check if attendees has sign in/out enabled.
if ($attendees->timecard) {
    if ($userid) { // Attempt to sign in / out someone else with userid.
        require_capability('mod/attendees:signinoutothers', $context);
        $message = attendees_signinout($attendees, $userid);
    } else if (has_capability('mod/attendees:signinout', $context)) { // Sign yourself in or out.
        if ($attendees->kioskmode && $code !== null) { // Sign in/out by code in kiosk mode.
            $return = attendees_lookup($attendees, $code);
            if (!empty($return) && is_numeric($return)) {
                $message = attendees_signinout($attendees, $return);
            } else {
                $message = $return;
            }
        } else { // Attempt to sign yourself in.
            $message = attendees_signinout($attendees, $USER->id);
        }
    }
}

// Completion and trigger events.
attendees_view($attendees, $course, $cm, $context);

$tab = !$tab ? $attendees->defaultview : $tab;
redirect($CFG->wwwroot ."/mod/attendees/view.php?id=$id&tab=$tab", $message);
