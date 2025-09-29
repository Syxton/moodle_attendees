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
 * Attendees library of functions.
 *
 * @package    mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$historylimit = 200;

/**
 * List of features supported in Attendees module
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function attendees_supports($feature) {

    if (!defined('FEATURE_MOD_PURPOSE')) {
        define('FEATURE_MOD_PURPOSE', 'mod_purpose');
    }
    if (!defined('MOD_PURPOSE_COLLABORATION')) {
        define('MOD_PURPOSE_COLLABORATION', 'collaboration');
    }

    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param array $data the data submitted from the reset course.
 * @return array status array
 */
function attendees_reset_userdata($data) {
    return [];
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function attendees_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function attendees_get_post_actions() {
    return ['update', 'add'];
}

/**
 * Add attendees instance.
 * @param stdClass $data
 * @param mod_attendees_mod_form $mform
 * @return int new attendees instance id
 */
function attendees_add_instance($data, $mform = null) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid = $data->coursemodule;

    $data->searchfields = serialize(clean_param_array($data->searchfields, PARAM_ALPHANUMEXT));
    $options = get_options_as_array($data);

    $data->displayoptions = serialize($options);
    $data->id = $DB->insert_record('attendees', $data);

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $cmid]);

    $compexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'attendees', $data->id, $compexpected);

    return $data->id;
}

/**
 * Return an array of options from the given object.
 *
 * @param stdClass $data The object containing the options.
 *
 * @return array An associative array of options.
 */
function get_options_as_array($data) {
    return [
        'timecard'          => $data->timecard,
        'autosignout'       => $data->autosignout,
        'defaultview'       => $data->defaultview,
        'showroster'        => $data->showroster,
        'lockview'          => $data->lockview,
        'kioskmode'         => $data->kioskmode,
        'kioskbuttons'      => $data->kioskbuttons,
        'separatelocations' => $data->separatelocations,
        'searchfields'      => $data->searchfields,
    ];
}

/**
 * Update attendees instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function attendees_update_instance($data, $mform) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid        = $data->coursemodule;
    $data->id    = $data->instance;
    $data->searchfields = serialize(clean_param_array($data->searchfields, PARAM_ALPHANUMEXT));

    $options = get_options_as_array($data);

    $data->displayoptions = serialize($options);

    $DB->update_record('attendees', $data);

    $compexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'attendees', $data->id, $compexpected);

    return true;
}

/**
 * Delete attendees instance.
 * @param int $id
 * @return bool true
 */
function attendees_delete_instance($id) {
    global $DB;

    if (!$attendees = $DB->get_record('attendees', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_instance('attendees', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'attendees', $id, null);

    // Note: all context files are deleted automatically.

    $DB->delete_records('attendees', ['id' => $attendees->id]);
    $DB->delete_records('attendees_timecard', ['aid' => $attendees->id]);

    return true;
}

/**
 * Sets dynamic information about a course module
 *
 * This function is called from cm_info when displaying the module
 * mod_attendees can have capabilities that should make in invisible to some users.
 *
 * @param cm_info $cm
 */
function attendees_cm_info_dynamic(cm_info $cm) {
    global $DB;
    $context = context_module::instance($cm->id);
    $viewrosters = has_capability('mod/attendees:viewrosters', $context);
    $attendees = $DB->get_record('attendees', ['id' => $cm->instance]);

    if (!$viewrosters && $attendees->kioskmode) {
        $cm->set_no_view_link();
        $cm->set_available(false);
    }
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info Info to customise main attendees display
 */
function attendees_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if (!$attendees = $DB->get_record('attendees', ['id' => $coursemodule->instance])) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $attendees->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('attendees', $attendees, $coursemodule->id, false);
    }

    return $info;
}

/**
 * Export attendees resource contents
 *
 * @param stdClass $cm      course module object
 * @return array            of file content
 */
function attendees_export_contents($cm) {
    global $DB;

    return $DB->get_record('attendees', ['id' => $cm->instance], '*', MUST_EXIST);
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param stdClass $attendees  attendees object
 * @param stdClass $course     course object
 * @param stdClass $cm         course module object
 * @param stdClass $context    context object
 * @since Moodle 3.0
 */
function attendees_view($attendees, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $attendees->id,
    ];

    $event = \mod_attendees\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('attendees', $attendees);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Generates the overwatch mode user interface.
 *
 * @param stdClass $cm       course module object
 * @param stdClass $attendees attendees object
 *
 * @return string            the overwatch mode user interface as HTML
 */
function attendees_overwatch_ui($cm, $attendees) {
    // Default overwatch button action.
    $params = ['id' => $cm->id, 'view' => "overwatch"];
    $url = new moodle_url('/mod/attendees/view.php', $params);

    $javascript = "window.location.href = '$url';";

    // If more than one location exists, require a location to be selected.
    if (empty($attendees->location)) {
        $locations = attendees_get_locations($cm);
        if (count($locations) > 1) {
            $javascript = "
            let locationvalue = jQuery('input.attendees_location:checked').val();
            if (locationvalue) {
                window.location.href = '$url&location=' + locationvalue;
            } else if (locationvalue === undefined || locationvalue === 0) {
                alert('You must select a location');
            }";
        }
    }

    $content = '
        <div class="attendees_menu_element" style="min-width: 200px;">
            <h3>Overwatch Mode</h3>
            <p style="font-size: 0.9em;">Use overwatch mode to administer and view the attendees list.</p>
            <button
                type="button"
                href="javascript: void(0);"
                onclick="' . $javascript . '">
                <i class="fa-solid fa-binoculars"></i> ' . get_string('overwatch', 'mod_attendees') . '
            </button>
        </div>';

    return $content;
}

/**
 * Given a course module object, this function returns the location id if it is the only location.
 *
 * @param stdClass $cm The course module record.
 * @return int|false The first unique location id if found, false otherwise.
 */
function attendees_get_only_location($cm) {
    $locations = attendees_get_locations($cm);
    if (count($locations) == 1) {
        foreach ($locations as $loc) {
            return $loc->id;
        }
    }
    return false;
}

/**
 * Get all unique locations for the activity.
 *
 * @param stdClass $cm The course module record.
 * @return array The recordset of location records.
 */
function attendees_get_locations($cm) {
    global $DB;

    $locations = $DB->get_records('attendees_locations', ["aid" => $cm->instance]);
    return $locations;
}

/**
 * Given an activity id, this function returns the first unique location id.
 *
 * @param int $aid The activity id.
 * @return int|false The first unique location id if found, false otherwise.
 */
function attendees_get_location($aid) {
    global $DB;

    $cm = get_coursemodule_from_instance('attendees', $aid);
    if (!$locations = attendees_get_locations($cm)) {
        return false;
    }

    // Return first location.
    foreach ($locations as $loc) {
        return $loc->id;
    }
}


/**
 * Given a location id, this function returns the location record if found.
 *
 * @param int $locid The location id.
 * @return stdClass The location record if found, throws a moodle_exception if not found.
 * @throws moodle_exception
 */
function attendees_get_this_location($locid) {
    global $DB;

    if (!$location = $DB->get_record_select('attendees_locations', 'id = ?', [$locid])) {
        throw new moodle_exception('invalidlocation', 'attendees');
    }
    return $location;
}

/**
 * Returns the HTML for the location manager UI.
 *
 * This function returns the HTML for the location manager UI, which is used to select the location
 * that the user will be signing attendees in and out of. The UI includes a radio button for each location,
 * and buttons for renaming, deleting, and adding new locations.
 *
 * @param stdClass $cm The course module record.
 * @param stdClass $attendees The attendees record.
 * @return string The HTML for the location manager UI.
 */
function attendees_location_manager_ui($cm, $attendees) {
    global $DB;

    $context = context_module::instance($cm->id);
    $canaddinstance = has_capability('mod/attendees:addinstance', $context);

    $locationlist = '';
    $locations = attendees_get_locations($cm);
    if ($locations) {
        $locationlistitems = '';
        // Build groupid array.
        foreach ($locations as $l) {
            $locationbuttons = "";
            if ($canaddinstance) {
                $params = ['id' => $cm->id, 'location' => $l->id];
                $url = new moodle_url('/mod/attendees/view.php', $params);

                // Selectors.
                $editing = '#locationsave_' . $l->id . ', #locationcancel_' . $l->id . ', #locationnameedit_' . $l->id;
                $notediting = '#locationname_' . $l->id . ', #locationrename_' . $l->id;

                $javascript = '
                window.location.href = \'' . $url . '&view=updatelocation&newname=\'' .
                ' + encodeURIComponent(jQuery(\'#locationnameedit_' . $l->id . '\').val());
                return false;';

                $locationbuttons = '
                <div>
                    <a  title="Rename" id="locationrename_' . $l->id . '"
                        href="javascript: void(0);"
                        class="btn"
                        style="color: #297e14ff;"
                        onclick="
                            jQuery(\'' . $notediting . '\').hide();
                            jQuery(\'' . $editing . '\').show();
                            jQuery(\'#locationnameedit_' . $l->id . '\').on(\'click\', function () {
                                jQuery(this).select();
                            }).trigger(\'click\');"
                    >
                        <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <a  title="Save"
                        id="locationsave_' . $l->id . '"
                        href="javascript: void(0);"
                        class="btn"
                        style="color: #5d3addff;display: none;"
                        onclick="' . $javascript . '"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>
                    </a>
                    <a  title="Cancel"
                        id="locationcancel_' . $l->id . '"
                        href="javascript: void(0);"
                        class="btn"
                        style="display: none;"
                        onclick="
                            jQuery(\'' . $editing . '\').hide();
                            jQuery(\'' . $notediting . '\').show();"
                    >
                        <i class="fa-solid fa-ban"></i>
                    </a>
                    <a  title="Delete"
                        href="javascript: void(0);"
                        class="btn"
                        style="color: #d33;"
                        onclick="
                            if (confirm(\'Are you sure you want to delete this location\')) {
                                window.location.href = \'' . $url . '&view=deletelocation\';
                            }"
                    >
                        <i class="fa-solid fa-trash"></i>
                    </a>
                </div>';
            }

            $locationlistitems .= '
                <div class="locationlist_item">
                    <div>
                        <input
                            type="radio"
                            name="attendees_location"
                            class="attendees_location"
                            value="' . $l->id . '"
                        />
                        <input
                            type="text"
                            id="locationnameedit_' . $l->id . '"
                            value="' . $l->name . '"
                            style="display: none;"
                        />
                        <span
                            id="locationname_' . $l->id . '"
                            style="padding: 4px;">
                            ' . $l->name . '
                        </span>
                    </div>
                    ' . $locationbuttons . '
                </div>
            ';
        }

        $locationlist = '
            <div class="locationlist">
                ' . $locationlistitems . '
            </div>';
    }

    // Add a new location button.
    if ($canaddinstance) {
        $params = ['id' => $cm->id, 'view' => "newlocation"];
        $url = new moodle_url('/mod/attendees/view.php', $params);

        $locationlist .= '
            <button
                type="button"
                onclick="window.location.href = \'' . $url . '\'">
                <i class="fa-solid fa-add"></i>
                Add New Location
            </button>';
    }

    $content = '
        <div class="attendees_menu_element">
            <h3>Location Selector</h3>
            <p style="font-size: 0.9em;">Select the location you will be signing attendees in and out of.</p>
            ' . $locationlist . '
        </div>';

    return $content;
}

/**
 * Generates the kiosk mode user interface.
 *
 * @param stdClass $cm       course module object
 * @param stdClass $attendees attendees object
 *
 * @return string            the kiosk mode user interface as HTML
 */
function attendees_start_kiosk_ui($cm, $attendees) {
    $params = ['id' => $cm->id, 'view' => "kiosk"];
    $url = new moodle_url('/mod/attendees/view.php', $params);

    // Default overwatch button action.
    $javascript = "window.location.href = '$url';";

    // If more than one location exists, require a location to be selected.
    if (empty($attendees->location)) {
        $locations = attendees_get_locations($cm);
        if (count($locations) > 1) {
            $javascript = "
            let locationvalue = jQuery('input.attendees_location:checked').val();
            if (locationvalue) {
                window.location.href = '$url&location=' + locationvalue;
            } else if (locationvalue === undefined || locationvalue === 0) {
                alert('You must select a location');
            }";
        }
    }

    $content = '
        <div class="attendees_menu_element">
            <h3>Kiosk Mode</h3>
            <p style="font-size: 0.9em;">Use kiosk mode to view the attendees list.</p>
            <button
                type="button"
                onclick="' . $javascript . '">
                <i class="fa-solid fa-tv"></i> ' . get_string('startkiosk', 'mod_attendees') . '
            </button>
        </div>';

    return $content;
}

/**
 * Generates the attendees menu user interface.
 *
 * This function checks if the user has the correct permissions and then
 * generates the menu based on those permissions.
 *
 * If the user has permission to view rosters, it will display the kiosk mode
 * button if the attendees instance has kiosk mode enabled. It will also
 * display the location manager button if the user has permission to add
 * instances.
 *
 * If the user has permission to add instances, it will display the overwatch
 * mode button.
 *
 * @param stdClass $cm       course module object
 * @param stdClass $attendees attendees object
 *
 * @return string            the attendees menu user interface as HTML
 */
function attendees_menu_ui($cm, $attendees) {
    $context = context_module::instance($cm->id);

    $canaddinstance = has_capability('mod/attendees:addinstance', $context);
    $canviewrosters = has_capability('mod/attendees:viewrosters', $context);

    // Only teachers should see this menu.
    if (!$canviewrosters) {
        // The user doesn't have permission to be here so go back to course page.
        $url = new moodle_url('/course/view.php', ['id' => $cm->course]);
        redirect($url);
    }

    $content = '<div class="attendees_menu">';

    if ($canviewrosters && $attendees->kioskmode) {
        $content .= attendees_start_kiosk_ui($cm, $attendees);
    }

    if ($canaddinstance) {
        $content .= attendees_overwatch_ui($cm, $attendees);
    }

    if ($canviewrosters) {
        $content .= attendees_location_manager_ui($cm, $attendees);
    }

    $content .= '</div>';

    return $content;
}

/**
 * Generates the user interface for the attendees module.
 *
 * This function takes a course module object and an attendees object as
 * parameters and generates the user interface based on those parameters.
 *
 * The user interface consists of a group selector, a sign in/out button
 * for students, a data history link, and a roster view.
 *
 * The function first checks if the user has permission to view rosters.
 * If the user has permission, it will display the kiosk mode button if
 * the attendees instance has kiosk mode enabled. It will also display
 * the location manager button if the user has permission to add instances.
 *
 * If the user has permission to add instances, it will display the overwatch
 * mode button.
 *
 * @param stdClass $cm          course module object
 * @param stdClass $attendees   attendees object
 * @param bool     $refresh     whether this is an AJAX roster refresh
 *
 * @return string               the user interface for the attendees module as HTML
 */
function attendees_get_ui($cm, $attendees, $refresh = false) {
    global $USER;

    $tab = !$attendees->tab ? 'all' : $attendees->tab;
    $groupid = !$attendees->group ? 0 : $attendees->group;

    $context = context_module::instance($cm->id);
    $viewrosters = has_capability('mod/attendees:viewrosters', $context);

    $content = "";
    $groupselector = "";

    // GROUP MODE.
    if ($groupmode = groups_get_activity_groupmode($cm)) {
        if (!$groupid) {
            $groupid = groups_get_activity_group($cm);
        }
        $allgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid);

        // Only show selector if there is more than 1 group to show.
        if (count($allgroups) > 1
            && !$refresh
            && (!$attendees->kioskmode
            || $attendees->view === "overwatch")) {

            $params = [
                'id' => $cm->id,
                'view' => $attendees->view,
                'location' => $attendees->location,
                'tab' => $tab,
            ];
            $url = new moodle_url('/mod/attendees/view.php', $params);

            $groupselector = groups_print_activity_menu($cm, $url, true);
        }
    }

    if (!$groupmode || (!$cm->groupingid && !$groupid)) { // All users.
        $users = get_enrolled_users($context, 'mod/attendees:signinout', 0, 'u.*', 'lastname ASC');
    } else if ($cm->groupingid && !$groupid) { // Show all users in grouping groups.
        $users = groups_get_grouping_members($cm->groupingid, 'u.*', 'lastname ASC');
    } else if ($groupid) { // Only show users in group.
        $users = get_enrolled_users($context, 'mod/attendees:signinout', $groupid, 'u.*', 'lastname ASC');
    }

    // Show single user sign in button if the timecard feature is being used.
    // And the user is a student.
    // And the activity is NOT in kiosk mode (shouldn't get this far).
    // And it isn't an ajax roster refresh.
    if (!$viewrosters
        && !$attendees->kioskmode
        && $attendees->timecard
        && !$refresh
        && is_enrolled($context, $USER, 'mod/attendees:signinout', true)
        && in_array($USER->id, array_column($users, 'id'))) {
        $content .= attendees_sign_inout_button($cm, $tab, $attendees);
    }

    // Data History link.
    if ($attendees->view !== "kiosk"
        && !$refresh
        && $attendees->timecard
        && has_capability('mod/attendees:viewhistory', $context)) {

        $params = ['id' => $cm->id, 'view' => 'history'];
        $url = new moodle_url('/mod/attendees/view.php', $params);
        $content .= '
        <div class="attendees_history">
            <a href="' . $url . '">
            ' . get_string('history', 'mod_attendees') . '
            </a>
        </div>';
    }

    if (!$refresh) {
        if (!$attendees->location) {
            // Auto select only location if only one exists and user cannot add instances.
            if (!$attendees->location = attendees_get_only_location($cm)) {
                $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id]);
                redirect($url, "No locations found");
            }
        }

        $location = attendees_get_this_location($attendees->location);
        $url = new moodle_url('/course/view.php', ['id' => $cm->course]);
        $content .= '
        <style>
            .attendees_hidden_link {
                color: initial;
                text-decoration: none;
            }
            .attendees_hidden_link:hover {
                text-decoration: none;
                color: initial;
                cursor: default;
            }
        </style>
        <h2>
            <a href="' . $url . '" class="attendees_hidden_link">
                <i class="fa-solid fa-location-dot"></i>
            </a> ' . $location->name . '
        </h2>
        ' . $groupselector;
    }

    // Roster.
    if ($viewrosters || $attendees->showroster) {
        if (has_capability('mod/attendees:signinout', $context)) {
            if (!$refresh && ($attendees->view === "overwatch" || !$attendees->lockview)) {
                $content .= attendees_roster_tabs($cm, $attendees);
            }
            $content .= attendees_roster_view($cm, $users, $attendees, $refresh);
        } else {
            $content .= attendees_roster_view($cm, $users, $attendees, $refresh);
        }
    } else {
        $content .= attendees_roster_view($cm, [$USER], $attendees, $refresh);
    }

    return $content;
}

/**
 * Generates the user interface for the roster tabs.
 *
 * @param cm_info $cm           course module data
 * @param stdClass $attendees   attendees object
 * @return string               user interface html text
 */
function attendees_roster_tabs($cm, $attendees) {
    global $CFG;
    $all = $onlyin = $onlyout = "";
    $tab = $attendees->tab;
    $$tab = 'active active_tree_node';
    $params = [
        'id' => $cm->id,
        'view' => $attendees->view,
        'location' => $attendees->location,
        'group' => $attendees->group,
    ];
    $url = new moodle_url('/mod/attendees/view.php', $params);
    $url .= '&tab=';

    return '
    <div class="attendees_tabs secondary-navigation d-print-none">
        <nav class="moremenu navigation observed" style="margin: 0">
            <ul class="nav more-nav nav-tabs">
                <li class="nav-item">
                    <a href="' . $url . 'all" class="nav-link ' . $all . '">
                        All Users
                    </a>
                </li>
                <li class="nav-item">
                    <a href="' . $url . 'onlyin" class="nav-link ' . $onlyin . '">
                        Active Users
                    </a>
                </li>
                <li class="nav-item">
                    <a href="' . $url . 'onlyout" class="nav-link ' . $onlyout . '">
                        Inactive Users
                    </a>
                </li>
            </ul>
        </nav>
    </div>';
}

/**
 * Generates the sign in/out button for the Attendees module.
 *
 * @param cm_info $cm           course module data
 * @param string $tab           the name of the selected tab
 * @param stdClass $attendees   attendees object
 * @return string               sign in/out button html text
 */
function attendees_sign_inout_button($cm, $tab, $attendees) {
    global $CFG, $USER, $DB, $OUTPUT;
    $user = $DB->get_record('user', ['id' => $USER->id], '*', MUST_EXIST);

    $params = [
        'id' => $cm->id,
        'tab' => $tab,
        'location' => $attendees->location,
        'view' => $attendees->view,
    ];
    $url = new moodle_url('/mod/attendees/action.php', $params);

    if (attendees_is_active($user, $attendees)) {
        $text = get_string("signout", "attendees");
        $inorout = $OUTPUT->pix_icon('a/logout', $text , 'moodle') . " $text";
    } else {
        $text = get_string("signin", "attendees");
        $inorout = $OUTPUT->pix_icon('withoutkey', $text , 'enrol_self') . " $text";
    }

    return '
        <div style="text-align:center">
            <a class="attendees_signinout_button" href="' . $url . '" alt="' . $text . '">
                ' . $inorout . '
            </a>
        </div>';
}

/**
 * Log the sign in or out action in the database.
 *
 * @param stdClass $attendees   attendees object
 * @param int $userid           user id
 * @return string               message output
 */
function attendees_signinout($attendees, $userid) {
    global $DB;
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $time = new DateTime("now", core_date::get_server_timezone_object());

    if (empty($attendees->location)) {
        if (!$attendees->location = attendees_get_location($attendees->id)) {
            throw new coding_exception('No location defined for this activity.');
        }
    }

    $timecard = (object) [
        'userid' => $user->id,
        'aid' => $attendees->id,
        'timelog' => $time->getTimestamp(),
        'ip' => getremoteaddr(),
        'location' => $attendees->location,
    ];

    if (attendees_is_active($user, $attendees)) {
        $timecard->event = "out";
        $DB->insert_record('attendees_timecard', $timecard);
    } else {
        $timecard->event = "in";
        $DB->insert_record('attendees_timecard', $timecard);
    }

    $a = new stdClass;
    $a->firstname = $user->firstname;
    $a->lastname = $user->lastname;
    return get_string("messagesigned" . $timecard->event, "attendees", $a);
}

/**
 * Get the current status of a user (in or out).
 *
 * @param stdClass $cm       course module data
 * @param stdClass $user     user data
 * @param stdClass $attendees attendees object
 * @return string            'in' or 'out'
 */
function attendees_current_status($cm, $user, $attendees) {
    return attendees_is_active($user, $attendees) ? "in" : "out";
}

/**
 * Checks if a user is currently signed in or out.
 *
 * This function first checks if the user has ever signed in or out.
 * If they have, it checks if the user has signed out on the current day.
 * If they have and the last action was a sign out, it returns false.
 * If they have and the last action was a sign in, it returns true.
 * If they have not signed in or out on the current day, it returns false.
 * If they have never signed in or out, it returns false.
 *
 * @param stdClass $user        user data
 * @param stdClass $attendees   attendees object
 * @return bool                 true if signed in, false if signed out
 */
function attendees_is_active($user, $attendees) {
    global $DB;

    $separatelocations = "";
    if ($attendees->separatelocations) {
        $separatelocations = "AND location = ?";
    }

    $sql = "SELECT * FROM {attendees_timecard}
        WHERE aid = ?
        AND event = ?
        AND userid = ?
        $separatelocations
        ORDER BY timelog
        DESC LIMIT 1";
    $lastout = $DB->get_record_sql($sql, [$attendees->id, 'out', $user->id, $attendees->location]);
    $lastin = $DB->get_record_sql($sql, [$attendees->id, 'in', $user->id, $attendees->location]);
    $today = attendees_get_today();

    if (!empty($lastin) || !empty($lastout)) {
        if ($attendees->autosignout) { // Auto signed out at the end of the day.
            if (!empty($lastout) && !empty($lastin) &&
                $lastout->timelog > $lastin->timelog && $lastout->timelog > $today) { // Have signed out today.
                return false;
            } else if (!empty($lastout) && !empty($lastin) &&
                      $lastin->timelog > $lastout->timelog && $today > $lastin->timelog) { // Haven't signed in today.
                return false;
            } else if ((!empty($lastout) && !empty($lastin) && $today > $lastout->timelog && $today > $lastin->timelog) ||
                        (empty($lastout) && !empty($lastin) && $today > $lastin->timelog)) { // New day.
                return false;
            } else if (empty($lastin)) { // Have never signed in.
                return false;
            }
            return true;
        } else { // No autosignout.
            if (!empty($lastout) && !empty($lastin) &&
                $lastout->timelog > $lastin->timelog) { // Last action was a sign out.
                return false;
            } else if (empty($lastin)) { // Have never signed in.
                return false;
            }
            return true;
        }
    }
    return false;
}

/**
 * Get beginning of day timestamp.
 *
 * @return int      unix timestamp
 */
function attendees_get_today() {
    $dateinmytimezone = new DateTime("now", core_date::get_server_timezone_object());
    $utcdate = new DateTime($dateinmytimezone->format("m/d/Y"), new DateTimeZone("UTC"));
    return $utcdate->getTimestamp();
}

/**
 * Attendees kiosk mode search.
 *
 * @param stdClass $attendees   attendees object
 * @param string $code          search string
 * @return string               returns either an output message or user id
 */
function attendees_lookup($attendees, $code) {
    global $DB;

    $code = trim($code);
    if (empty($code)) {
        return;
    }
    $cm = get_coursemodule_from_instance('attendees', $attendees->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // GROUP MODE.
    $groupid = []; // By default search ALL groups.
    if ($groupmode = groups_get_activity_groupmode($cm)) {
        // See if a signle group is selected for the activity.
        $groupid = groups_get_activity_group($cm);
        // If no single group is selected, go back to all groups.
        $groupid = $groupid === 0 ? [] : $groupid;
    }

    // If grouping is set and it contains more than 1 group, groupid should be an array of all groups in the grouping.
    if ($groupmode && $cm->groupingid && empty($groupid)) {
        $groups = $DB->get_records_list('groupings_groups', 'groupingid', [$cm->groupingid]);
        // Build groupid array.
        foreach ($groups as $g) {
            $groupid[] = $g->groupid;
        }
    }

    // Find which user fields we will try to match on.
    $searchfields = (array) unserialize_array($attendees->searchfields);
    if (empty($searchfields)) { // If empty, search all fields.
        $searchfields = ['idnumber', 'email', 'username', 'phone1', 'phone2'];
    }

    // Create search sql and parameters.
    $searchsql = '';
    $searchparams = [];
    for ($sp = 0; $sp < count($searchfields); $sp++) {
        $searchsql .= empty($searchsql) ? '' : ' OR ';
        $searchsql .= "u." . $searchfields[$sp] . ' = :code' . $sp;
        $searchparams["code$sp"] = $code;
    }

    list($esql, $params) = get_enrolled_sql($context, 'mod/attendees:signinout', $groupid, true);
    $sql = "SELECT u.id
            FROM {user} u
            JOIN ($esql) je ON je.id = u.id
            WHERE u.deleted = 0
            AND ($searchsql)";

    $params = array_merge($params, $searchparams);

    // Perform sql search.
    $results = $DB->get_records_sql($sql, $params);

    // Must match only 1 student.
    if (count($results) === 1) {
        return reset($results)->id;
    }

    return get_string("codenotfound", "attendees");
}


/**
 * Generates the user interface for the roster view.
 *
 * @param stdClass $cm          course module data
 * @param array $users         array of user objects to display
 * @param stdClass $attendees   attendees object
 * @param bool $refresh        whether to refresh the entire page content or just the roster
 * @return string               user interface html text
 */
function attendees_roster_view($cm, $users, $attendees, $refresh = false) {
    global $CFG, $OUTPUT, $DB;
    require_once($CFG->libdir . '/filelib.php');

    $context = context_module::instance($cm->id);
    $signinoutothers = has_capability('mod/attendees:signinoutothers', $context);

    $params = [
        'id' => $cm->id,
        'tab' => $attendees->tab,
        'location' => $attendees->location,
        'view' => $attendees->view,
    ];
    $url = new moodle_url('/mod/attendees/action.php', $params);

    $output = "";

    // Add search mode for kiosk with timecards.
    if ($attendees->kioskmode && $attendees->timecard && !$refresh) {
        $output .= '
            <div class="attendees_usersearch">
                <form method="get" action="' . $url . '" style="padding: 10px;width: 450px;margin: auto;">
                    <input  type="hidden"
                            name="id"
                            id="id"
                            value="' . $cm->id . '" />
                    <input  type="hidden"
                            name="tab"
                            id="tab"
                            value="' . $attendees->tab . '" />
                    <input  type="hidden"
                            name="location"
                            id="location"
                            value="' . $attendees->location . '" />
                    <input  type="hidden"
                            name="view"
                            id="view"
                            value="' . $attendees->view . '" />
                    <label style="font-weight: bold;">
                    ' . get_string("usersearch", "attendees") . '
                    </label>
                    <input  class="form-control attendees_search"
                            type="password"
                            name="code"
                            id="code"
                            style="margin: 0 5px;"
                            onblur="setInterval(function() {
                                        if (document.activeElement === document.body) {
                                            document.getElementById(\'code\').focus();
                                        }
                                    } , 100);"
                    />
                    <input  class="btn btn-primary"
                            type="submit"
                            style="vertical-align: top;"
                            value="' . get_string("signinout", "attendees") . '" />
                </form>
                <script>
                    document.getElementById("code").focus();
                </script>
            </div>';
    }

    $options = [
        'size' => '100', // Size of image.
        'link' => !$attendees->kioskmode, // Make image clickable.
        'alttext' => true, // Add image alt attribute.
        'class' => "userpicture", // Image class attribute.
        'visibletoscreenreaders' => false,
    ];

    // Reduce users array if possible.
    if ($attendees->tab !== "all") {
        $users = filteroutusers($attendees, $users, $attendees->tab);
    }

    $useroutput = "";
    foreach ($users as $user) {
        $status = "out";
        if ($attendees->timecard) {
            $status = attendees_current_status($cm, $user, $attendees);
        }

        // Only show icons if timecard is enabled and has permissions.
        $icons = "";
        if (!empty($attendees->timecard)
            && !empty($signinoutothers)
            && (empty($attendees->kioskmode)
                || (!empty($attendees->kioskmode) && !empty($attendees->kioskbuttons)
                || $attendees->view === "overwatch")
            )) {

            $icons = '
                <a  class="attendees_otherinout_button"
                    href="' . $url . "&userid=$user->id" . '"
                    alt="' . get_string("signinout", "attendees") . '">
                    ' . $OUTPUT->pix_icon('a/logout', get_string("signout", "attendees"), 'moodle') . '
                    ' . $OUTPUT->pix_icon('withoutkey', get_string("signin", "attendees"), 'enrol_self') . '
                </a>';
        }

        $useroutput .= '
            <div class="attendees_userblock attendees_status_'. $status . '">
                ' . $icons . '
                ' . $OUTPUT->user_picture($user, $options) . '
                <div class="attendees_name">
                    ' . $user->firstname . ' ' . $user->lastname . '
                </div>
                ' . attendees_list_user_groups($cm, $attendees, $user->id) . '
            </div>';
    }

    return '<div class="attendees_refreshable">' . $useroutput . '</div>';
}


/**
 * Filter out users based on current sign in status and settings.
 *
 * This function takes an array of all users and filters out users based on the following criteria:
 * - If autosignout is enabled, filter out users who have not signed in recently.
 * - If tab is set to "onlyin", verify active users are in the enrolled users list.
 * - If tab is set to "onlyout", subtract the active users list from the all users list.
 *
 * @param stdClass $attendees The module instance settings.
 * @param array $allusers An array of all users.
 * @return array The filtered array of users.
 */
function filteroutusers($attendees, $allusers): array {
    global $DB;

    $timelimit = 0;
    if ($attendees->autosignout) {
        $timelimit = attendees_get_today();
    }

    $params = [
        $attendees->id,
        'in',
        $timelimit,
        $attendees->id,
    ];

    $separatelocations = "";
    if ($attendees->separatelocations) {
        $separatelocations = "AND location = ?";
        $params[] = $attendees->location; // Add location to parameters.
        $params[] = $attendees->location; // Add twice since we use it twice in the query.
    }

    // Find all currently signed in users.
    $sql = "SELECT u.*, tc.aid, tc.event, tc.timelog
            FROM {user} u
            INNER JOIN {attendees_timecard} tc ON u.id = tc.userid
            WHERE tc.aid = ?
            AND tc.event = ?
            AND tc.timelog >= ?
            AND tc.timelog IN ( SELECT MAX(timelog)
                                FROM {attendees_timecard} t
                                WHERE t.userid = tc.userid
                                AND t.aid = ?
                                $separatelocations)
           $separatelocations
          ORDER BY u.lastname";

    $activeusers = $DB->get_records_sql($sql, $params);

    // A user could have been signed in recently, but removed from a group more recently.
    if ($attendees->tab == "onlyin") {
        // Verify active users are in the enrolled users list.
        foreach ($activeusers as $id => $auser) {
            if (!isset($allusers[$id])) {
                unset($activeusers[$id]);
            }
        }
        return $activeusers;
    } else if ($attendees->tab == "onlyout") {
        // Subtract the activeusers list from the allusers list.
        foreach ($activeusers as $id => $auser) {
            if (isset($allusers[$id])) {
                unset($allusers[$id]);
            }
        }
    }
    return $allusers;
}

/**
 * List groups that user is a member of.
 *
 * @param cm_info $cm           course module data
 * @param stdClass $attendees   attendees object
 * @param int $userid           user id
 * @return string               output list of groups
 */
function attendees_list_user_groups($cm, $attendees, $userid): string {
    $grouplist = "";
    if ($attendees->showgroups) {
        $groupings = groups_get_user_groups($cm->course, $userid);
        foreach ($groupings[0] as $group) {
            $grouplist .= "<div class='attendees_group'>" . groups_get_group_name($group) . "</div>";
        }
    }

    return '<div class="attendees_groups">' . $grouplist . '</div>';
}

/**
 * Provides the UI for the attendees history view.
 *
 * @param cm_info $cm The course module object.
 * @param stdClass $attendees The attendees object.
 * @return string The rendered output.
 */
function attendees_history_ui($cm, $attendees) {
    // URL for the main view of the attendees module.
    $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id]);

    // Instantiate the myform form from within the plugin.
    $mform = new \mod_attendees\form\historyform(null, ['cm' => $cm, 'attendees' => $attendees]);
    $formdata = null;
    $vars = ['h_to' => 0, 'h_from' => 0, 'h_user' => 0, 'h_locations' => 0, 'h_courses' => 0, 'h_page' => 0];

    // Form processing and displaying is done here.
    if ($mform->is_cancelled()) {
        // If the cancel element was pressed, then exit early.
        return '';
    } else if ($formdata = $mform->get_data()) {
        // When the form is submitted, and the data is successfully validated,
        // the `get_data()` function will return the data posted in the form.
        $vars['h_from'] = isset($formdata->h_from) ? $formdata->h_from : 0;
        $vars['h_to'] = isset($formdata->h_to) ? $formdata->h_to : 0;
        $vars['h_user'] = !empty($formdata->h_user) ? $formdata->h_user : 0;
        $vars['h_locations'] = !empty($formdata->h_locations) ? $formdata->h_locations : 0;
        $vars['h_courses'] = !empty($formdata->h_courses) ? $formdata->h_courses : 0;
        $vars['h_page'] = isset($formdata->h_page) ? $formdata->h_page : 0;
    }

    // Set any default data (if any).
    $mform->set_data($formdata);

    // Back to main view.
    return '
        <div>
            <a href="' . $url . '">
                <strong>' . get_string('returntoattendees', 'attendees') . '</strong>
            </a>
            <br><br>
        </div>
        <div style="max-width:600px;margin:auto;">
        ' . $mform->render() . '
        </div>
        ' . get_history($attendees, $vars); // Get history from database.
}

/**
 * Get the history of attendees.
 *
 * This function gets the history of attendees for the given attendees
 * object and returns it as an HTML table. The query is constructed
 * from the given query parameters.
 *
 * @param stdClass $attendees The attendees object.
 * @param array $vars The query parameters.
 * @return string The HTML table containing the history.
 */
function get_history(stdClass $attendees, array $vars): string {
    global $DB, $historylimit;

    // Initialize the query parameters.
    $params['id'] = $attendees->id;

    // Initialize the SQL WHERE conditions.
    $timesql = $usersql = $locationsql = $ejoin = '';

    // If times are given.
    if (!empty($vars['h_from']) || isset($vars['h_to'])) {
        // Add the time condition to the SQL query if times are given.
        if (!empty($vars['h_from'])) {
            $timesql .= ' AND t.timelog >= :from';
            $params['from'] = $vars['h_from'];
        }

        if (!empty($vars['h_to'])) {
            $timesql .= ' AND t.timelog <= :to';
            $params['to'] = $vars['h_to'];
        }
    }

    // If user is given.
    if (!empty($vars['h_user'])) {
        // Add the user condition to the SQL query if a user is given.
        [$usql, $up] = $DB->get_in_or_equal($vars['h_user'], SQL_PARAMS_NAMED, 'user');
        $usersql = "AND t.userid {$usql}";
        $params += $up;
    }

    // If location is given.
    if (!empty($vars['h_locations'])) {
        // Add the location condition to the SQL query if a location is given.
        [$locsql, $locp] = $DB->get_in_or_equal($vars['h_locations'], SQL_PARAMS_NAMED, 'location');
        $locationsql = "AND t.location {$locsql}";
        $params += $locp;
    }

    // If courses are given.
    if (!empty($vars['h_courses'])) {
        // Add the course condition to the SQL query if a course is given.
        [$csql, $cp] = $DB->get_in_or_equal($vars['h_courses'], SQL_PARAMS_NAMED, 'courses');
        $ejoin = "JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.courseid {$csql})";
        $params += $cp;
    }

    $pagenum = !empty($vars['h_page']) ? $vars['h_page'] : 0;
    $offset = $pagenum * $historylimit;

    // Build the SQL query.
    $sql = "SELECT t.timelog, t.event, u.id, u.firstname, u.lastname, t.aid, t.ip, l.id as lid, l.name as locationname
            FROM {attendees_timecard} t
            JOIN {user} u ON u.id = t.userid
            JOIN {attendees_locations} l ON l.id = t.location
            $ejoin
            WHERE t.aid = :id
            AND t.event = 'in'
            $timesql
            $usersql
            $locationsql
            ORDER BY t.timelog DESC
            LIMIT " . ($historylimit + 1) . " OFFSET $offset";

    // Execute the SQL query and get the results.
    if ($results = $DB->get_records_sql($sql, $params)) {
        // Convert the results to a HTML table and return it as the answer.
        return display_history($results, $attendees, $vars);
    } else {
        // If the query returned no results, set the answer to
        // indicate that.
        return 'No results found.';
    }
}

/**
 * Convert an array of login records to an HTML table for display in the history page.
 *
 * @param array $history The array of login records.
 * @param stdClass $attendees The attendees object.
 * @param array $vars An array of query parameters, containing the following:
 *     - `h_courses`: The courses for the query (optional).
 *
 * @return string The HTML table containing the history.
 */
function display_history(array $history, stdClass $attendees, array $vars): string {
    global $historylimit;

    // Initialize the return string.
    $return = "";
    $click = 'document.getElementById(\'filterattendees\').click();';
    $pageinput = 'document.getElementsByName(\'h_page\')[0].value';

    $count = count($history);
    if ($vars['h_page'] > 0 || $count > $historylimit) {
        // Paginated results.
        $return .= '<div class="pagination">';
        $return .= '<ul class="pagination" style="margin: 1em auto">';

        if ($vars['h_page'] > 0) {
            $return .= '<li class="page-item">
                            <a class="page-link" href="#"
                               onclick="' . $pageinput . '=0;' . $click . '">'
                               . get_string('first') .
                            '</a>
                        </li>';
            $return .= '<li class="page-item">
                            <a class="page-link" href="#"
                               onclick="' . $pageinput . '=' . ($vars['h_page'] - 1) . ';' . $click . '">'
                               . get_string('previous') .
                            '</a>
                        </li>';
        }

        if ($count > $historylimit) {
            $return .= '<li class="page-item">
                            <a class="page-link" href="#"
                               onclick="' . $pageinput . '=' . ($vars['h_page'] + 1) . ';' . $click . '">'
                               . get_string('next') .
                            '</a>
                        </li>';
        }

        $return .= '</ul>';
        $return .= '</div>';
    }

    $return .= '<table style="width:100%" class="generaltable">';
    // Add table headers.
    $return .= '<tr>
        <th>' . get_string('user') . '</th>
        <th>' . get_string('locations', 'attendees') . '</th>
        <th>' . get_string('signedin', 'attendees') . '</th>
        <th>' . get_string('signedout', 'attendees') . '</th>
        <th>' . get_string('duration', 'attendees') . '</th>
    </tr>';

    // Initialize the loop counter.
    $loop = 0;

    // Convert the results to an HTML table.
    foreach ($history as $login) {
        $return .= '<tr>
            <td>' . $login->firstname . ' ' . $login->lastname . '</td>
            <td>' . $login->locationname . '</td>
            <td>' . date("F j, Y, g:i a", $login->timelog) . '</td>';

        // Get next sign in and next signouts.
        $nextin = get_users_next_signin($attendees, $login);
        $nextout = get_users_next_signout($attendees, $login);

        // If signed out time is today, get duration.
        if ($nextout) {
            // If next signin is between last signin and signed out time, assume autosignout.
            if ($nextin && ($nextin->timelog > $login->timelog && $nextin->timelog < $nextout->timelog)) {
                $return .= '<td>' .
                    get_string('nosignout', 'attendees') .
                    '</td><td>--</td>';
            } else {
                $return .= '<td>' .
                    date("F j, Y, g:i a", $nextout->timelog) .
                    '</td><td>' .
                    get_duration($nextout->timelog - $login->timelog) .
                    '</td>';
            }
        } else { // Didn't log out.
            // If signed in time is today, get duration.
            if (date("F j, Y") == date("F j, Y", $login->timelog)) {
                $return .= '<td>' .
                    get_string('signedin', 'attendees') .
                    '</td><td>' .
                    get_duration(time() - $login->timelog) .
                    '</td>';
            } else {
                // If not autosigned out, show duration.
                if (!$attendees->autosignout) {
                    if ($nextin) {
                        $return .= '<td>' .
                            get_string('nosignout', 'attendees') .
                            '</td><td>--</td>';
                    } else {
                        $return .= '<td>' .
                            get_string('signedin', 'attendees') .
                            '</td><td>' .
                            get_duration(time() - $login->timelog) .
                            '</td>';
                    }
                } else {
                    $return .= '<td>' . get_string('nosignout', 'attendees') . '</td><td>--</td>';
                }
            }
        }

        $return .= '</tr>';

        $loop++;
        if ($loop >= $historylimit) {
            break;
        }
    }
    $return .= '</table>';

    return $return;
}

/**
 * Gets the next time the user signed in for the specified attendees event.
 *
 * This function takes an attendees object and a user's login record, with the fields 'id' and 'ip',
 * and returns the next time the user signed in, or false if none found.
 *
 * @param stdClass $attendees The attendees object.
 * @param stdClass $login The user's login object containing their ID and IP address.
 *
 * @return stdClass|false The next time the user signed in or false if none found.
 */
function get_users_next_signin($attendees, $login) {
    global $DB;

    $params = [];

    $locationsql = ""; // If multiple locations is enabled.
    if ($attendees->separatelocations) {
        [$locsql, $locp] = $DB->get_in_or_equal($login->lid, SQL_PARAMS_NAMED, 'location');
        $locationsql = " AND t.location {$locsql}";
        $params += $locp;
    }

    // Have they signed in again since? Assume autosignout. Should only happen if autosignout setting has been changed.
    $sql = "SELECT t.timelog, t.event, t.userid
            FROM {attendees_timecard} t
            WHERE t.aid = :id
            $locationsql
            AND t.event = 'in'
            AND t.userid = :userid
            AND t.timelog > :timein
            ORDER BY t.timelog ASC
            LIMIT 1";

    $params['id'] = $attendees->id;
    $params['userid'] = $login->id;
    $params['timein'] = $login->timelog;

    return $DB->get_record_sql($sql, $params);
}

/**
 * Gets the next time the user signed out for the specified attendees event.
 *
 * @param stdClass $attendees The attendees object.
 * @param stdClass $login The user's login object containing their ID and IP address.
 *
 * @return stdClass|false The next time the user signed out or false if none found.
 */
function get_users_next_signout($attendees, $login) {
    global $DB;

    $params = [];

    $locationsql = ""; // If multiple locations is enabled.
    if ($attendees->separatelocations) {
        [$locsql, $locp] = $DB->get_in_or_equal($login->lid, SQL_PARAMS_NAMED, 'location');
        $locationsql = " AND t.location {$locsql}";
        $params += $locp;
    }

    // Get users next logout time.
    $sql = "SELECT t.timelog, t.event, t.userid
            FROM {attendees_timecard} t
            WHERE t.aid = :id
            $locationsql
            AND t.event = 'out'
            AND t.userid = :userid
            AND t.timelog > :timein
            ORDER BY t.timelog ASC
            LIMIT 1";

    $params['id'] = $attendees->id;
    $params['userid'] = $login->id;
    $params['timein'] = $login->timelog;

    return $DB->get_record_sql($sql, $params);
}

/**
 * Returns a human-readable duration given a number of seconds.
 *
 * @param int $seconds The number of seconds to convert.
 *
 * @return string A duration in the format H:i:s (hours:minutes:seconds).
 */
function get_duration($seconds) {
    // Convert the number of seconds to a human-readable string.
    // If seconds are over 24 hours, display the number of days.
    if ($seconds > 86400) {
        $days = floor($seconds / 86400);
        $seconds = $seconds - ($days * 86400);
        return $days . 'd ' . get_duration($seconds);
    } else {
        return gmdate('H\h i\m s\s', $seconds);
    }
}

