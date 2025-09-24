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

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/attendees/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id         = optional_param('id', 0, PARAM_INT);
$group      = optional_param('group', 0, PARAM_INT);
$p          = optional_param('p', 0, PARAM_INT);
$tab        = optional_param('tab', false, PARAM_ALPHANUM);
$view       = optional_param('view', null, PARAM_ALPHANUM);
$location   = optional_param('location', 0, PARAM_INT); // Location filter.

if ($p) {
    if (!$attendees = $DB->get_record('attendees', ['id' => $p])) {
        throw new \moodle_exception('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('attendees', $attendees->id, $attendees->course, false, MUST_EXIST);
} else {
    if (!$cm = get_coursemodule_from_id('attendees', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    $attendees = $DB->get_record('attendees', ['id' => $cm->instance], '*', MUST_EXIST);
}

$attendees->view = $view;
$attendees->location = $location;
$attendees->group = $group;

$attendees->tab = !$tab ? $attendees->defaultview : $tab;
$attendees->tab = $attendees->lockview && !$attendees->view == "overwatch" ? $attendees->defaultview : $attendees->tab;

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendees:view', $context);

// Completion and trigger events.
attendees_view($attendees, $course, $cm, $context);

$options = empty($attendees->displayoptions) ? [] : (array) unserialize_array($attendees->displayoptions);

$activityheader = ['hidecompletion' => false];
if (empty($options['printintro'])) {
    $activityheader['description'] = '';
}

if ($attendees->multiplelocations && !$location && !$attendees->view) {
    $attendees->view = "menu"; // Force menu view if multiple locations and no location selected.
}

$PAGE->set_title($course->shortname.': ' . $attendees->name);
$PAGE->set_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => $attendees->view, 'location' => $attendees->location, 'tab' => $attendees->tab, 'group' => $attendees->group]);

$PAGE->add_body_class('limitedwidth');

// These views / actions are restricted to users with viewrosters capability.
$restrictedview = [
    "menu",
    "history",
    "overwatch",
    "newlocation",
    "updatelocation",
    "deletelocation",
];

if ($attendees->kioskmode || in_array($attendees->view, $restrictedview, true)) {
    if (!has_capability('mod/attendees:viewrosters', $context)) {
        // The user doesn't have permission to be here so go back to course page.
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        redirect($url);
    }

    // Kiosk mode or restricted view so use secure layout.
    if ($attendees->view === "kiosk") {
        $PAGE->set_pagelayout('secure'); // Reduced header.
    }
}

$PAGE->activityheader->set_attrs($activityheader);
$PAGE->activityheader->disable();

// Auto update and keep alive for overwatch and kiosk mode.
$auto_update = '
    <iframe id="attendees_keepalive" src="' . $CFG->wwwroot . '"></iframe>
    <script type="module">
        require(["jquery"], function (jQuery) {
            window.setInterval(() => {
                document.getElementById("attendees_keepalive").contentWindow.location.reload();
            }, 60000);

            jQuery(document).bind("contextmenu", function(e) {
                return false;
            });

            window.setInterval(() => {
                jQuery(".notifications button.btn-close").click();
            }, 5000);

            window.setInterval(() => {
                jQuery.ajax({
                    url : "./ajax.php",
                    type : "GET",
                    data : {
                        "id" : ' . $id . ',
                        "tab" : "' . $attendees->tab . '",
                        "group" : ' . $attendees->group . ',
                        "location" : ' . $attendees->location . ',
                        "view" : "' . $attendees->view . '"
                    },
                    dataType: "json",
                    success : function(data) {
                        jQuery(".attendees_refreshable").html(data);
                    },
                    error : function(request, error) {
                        console.log("Request: " + JSON.stringify(request));
                    }
                });
            }, 10000);
        });
    </script>
';

$content = $OUTPUT->header();
if ($attendees->view === "newlocation") {
    $location = [
        'aid' => $attendees->id,
        'name' => "New Location",
    ];
    $DB->insert_record('attendees_locations', $location);
    $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => 'menu']);
    redirect($url);
}

if ($attendees->view === "updatelocation" && $location) {
    $newname = optional_param('newname', false, PARAM_TEXT);
    if ($newname) {
        $location = [
            'id' => $location,
            'name' => $newname,
        ];
        $DB->update_record('attendees_locations', $location);
    }

    $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => 'menu']);
    redirect($url);
}

if ($attendees->view === "deletelocation" && $location) {
    $params = [
        'id' => $location,
    ];
    $DB->delete_records('attendees_locations', $params);
    $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => 'menu']);
    redirect($url);
}

if ($attendees->view === "history") {
    if (has_capability('mod/attendees:viewhistory', $context)) {
        $attendees->name = get_string('history', 'attendees');
        $attendees->intro = get_string('historydesc', 'attendees');
        $content .= attendees_history_ui($cm, $attendees);
    } else {
        // The user doesn't have permission to be here.
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        redirect($url);
    }
} else if ($attendees->view === "overwatch") {
    $content .= $auto_update . attendees_get_ui($cm, $attendees);
} else if ($attendees->kioskmode && $attendees->view === "kiosk") {
    $content .= '
        <div class="attendees_kioskmode">
            <p>
            ' . $attendees->intro . '
            </p>
            ' . $auto_update . '
            ' . attendees_get_ui($cm, $attendees) . '
        </div>';
} else if (($attendees->kioskmode && $attendees->view == "menu") || ($attendees->multiplelocations && !$location)) {
    $content .= attendees_menu_ui($cm, $attendees);
} else {
    $content .= $auto_update . attendees_get_ui($cm, $attendees);
}

$formatoptions = (object) [
    'context' => $context,
    'noclean' => true,
    'overflowdiv' => true,
];
$content = format_text($content, FORMAT_HTML, $formatoptions);

echo $OUTPUT->box($content, "center");
echo $OUTPUT->footer();
