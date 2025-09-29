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

// Standard passed variables.
$id         = optional_param('id', 0, PARAM_INT); // Module ID.
$group      = optional_param('group', 0, PARAM_INT);
$tab        = optional_param('tab', false, PARAM_ALPHANUM);
$view       = optional_param('view', "menu", PARAM_ALPHANUM);
$location   = optional_param('location', 0, PARAM_INT); // Location filter.

// Get the course module.
if (!$cm = get_coursemodule_from_id('attendees', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}

// Get the course.
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Check if the user can view the module.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendees:view', $context);

// Get the attendees record.
$attendees = $DB->get_record('attendees', ['id' => $cm->instance], '*', MUST_EXIST);
$attendees->view = $view;
$attendees->location = $location;
$attendees->group = $group;
$attendees->tab = !$tab ? $attendees->defaultview : $tab;
$attendees->tab = $attendees->lockview && !$attendees->view == "overwatch" ? $attendees->defaultview : $attendees->tab;

// Completion and trigger events.
attendees_view($attendees, $course, $cm, $context);

$options = empty($attendees->displayoptions) ? [] : (array) unserialize_array($attendees->displayoptions);

$activityheader = ['hidecompletion' => false];
if (empty($options['printintro'])) {
    $activityheader['description'] = '';
}

$PAGE->set_title($course->shortname.': ' . $attendees->name);
$PAGE->set_url('/mod/attendees/view.php', [
    'id' => $cm->id,
    'view' => $attendees->view,
    'location' => $attendees->location,
    'tab' => $attendees->tab,
    'group' => $attendees->group,
]);
$PAGE->add_body_class('limitedwidth');
$PAGE->activityheader->set_attrs($activityheader);
$PAGE->activityheader->disable();

// Security Area.
$canaddinstance = has_capability('mod/attendees:addinstance', $context);
$canviewrosters = has_capability('mod/attendees:viewrosters', $context);
$canviewhistory = has_capability('mod/attendees:viewhistory', $context);

// If using separate locations and no location selected, force menu view.
if (empty($attendees->location) && empty($attendees->view)) {
    $attendees->view = "menu";
}

$restrictedview = [ // Restricted to users with addinstance capability.
    "overwatch",
    "newlocation",
    "updatelocation",
    "deletelocation",
];
if (in_array($attendees->view, $restrictedview, true)) {
    if (!$canaddinstance) {
        // The user doesn't have permission to be here so go back to course page.
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        redirect($url, get_string('nopermissiontoaccesspage', 'error'));
    }
}

$restrictedview = [ // Restricted to users with addinstance capability.
    "menu",
    "kiosk",
];
if (in_array($attendees->view, $restrictedview, true)) {
    if (!$canviewrosters) {
        // The user doesn't have permission to be here so go back to course page.
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        redirect($url, get_string('nopermissiontoaccesspage', 'error'));
    }
}

// Check for valid location data.
if ($attendees->timecard && !$locations = attendees_get_locations($cm)) { // No locations found.
    if ($attendees->view !== "menu" && $attendees->view !== "newlocation") { // Only the menu is allowed.
        $attendees->view = "menu";
        if (!$canaddinstance) {
            // The user doesn't have permission to be here so go back to course page.
            $url = new moodle_url('/course/view.php', ['id' => $course->id]);
            redirect($url, get_string('notsetup', 'attendees'));
        }
        \core\notification::info(get_string('locationdatamissing', 'attendees'));
    }
}

// Kiosk mode uses secure layout.
if ($attendees->kioskmode && $attendees->view === "kiosk") {
    if (!$canviewrosters) {
        // The user doesn't have permission to be here so go back to course page.
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        redirect($url, get_string('nopermissiontoaccesspage', 'error'));
    }

    // Kiosk mode or restricted view so use secure layout.
    $PAGE->set_pagelayout('secure'); // Reduced header.
}

// Auto update and keep alive for overwatch and kiosk mode.
$autoupdate = '
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

$content = "";
switch ($attendees->view) {
    case "menu":
        $content .= attendees_menu_ui($cm, $attendees);
        break;
    case "kiosk":
        if ($attendees->kioskmode) {
            $content .= '
            <div class="attendees_kioskmode">
                <p>
                ' . $attendees->intro . '
                </p>
                ' . $autoupdate . '
                ' . attendees_get_ui($cm, $attendees) . '
            </div>';
        } else {
            // Kiosk mode is not enabled so go back to normal view.
            $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id]);
            redirect($url, get_string("viewnotavailable", "attendees"));
        }
        break;
    case "history":
        if (has_capability('mod/attendees:viewhistory', $context)) {
            $attendees->name = get_string('history', 'attendees');
            $attendees->intro = get_string('historydesc', 'attendees');
            $content .= attendees_history_ui($cm, $attendees);
        } else {
            // The user doesn't have permission to be here.
            $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id]);
            redirect($url, get_string('nopermissiontoaccesspage', 'error'));
        }
        break;
    case "overwatch":
        $content .= $autoupdate . attendees_get_ui($cm, $attendees);
        break;
    case "newlocation":
        $location = [
            'aid' => $attendees->id,
            'name' => "New Location",
        ];
        $DB->insert_record('attendees_locations', $location);
        $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => 'menu']);
        redirect($url, get_string('locationadded', 'attendees'));
        break;
    case "updatelocation":
        if ($location) {
            $newname = optional_param('newname', false, PARAM_TEXT);
            if ($newname) {
                $location = [
                    'id' => $location,
                    'name' => $newname,
                ];
                $DB->update_record('attendees_locations', $location);
            }

            $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => 'menu']);
            redirect($url, get_string('locationupdated', 'attendees'));
        } else {
            // No location specified so go back to menu.
            $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => 'menu']);
            redirect($url, get_string('locationdatamissing', 'attendees'));
        }
        break;
    case "deletelocation":
        if ($location) {
            // Cannot delete last location.
            if (count($locations) == 1) {
                $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => 'menu']);
                redirect($url, get_string('cannotdeletelastlocation', 'attendees'));
            }

            // Delete location.
            $DB->delete_records('attendees_locations', ['id' => $location]);
            $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => 'menu']);
            redirect($url, get_string('locationdeleted', 'attendees'));
        } else {
            // No location specified so go back to menu.
            $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id, 'view' => 'menu']);
            redirect($url, get_string('locationdatamissing', 'attendees'));
        }
        break;
    default:
        // Default view is the normal roster view.
        $content .= $autoupdate . attendees_get_ui($cm, $attendees);
        break;
}

$formatoptions = (object) [
    'context' => $context,
    'noclean' => true,
    'overflowdiv' => true,
];
$content = format_text($content, FORMAT_HTML, $formatoptions);

echo $OUTPUT->header();
echo $OUTPUT->box($content, "center");
echo $OUTPUT->footer();
