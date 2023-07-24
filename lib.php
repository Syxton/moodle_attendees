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
 * @package mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * List of features supported in Attendees module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function attendees_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_MOD_PURPOSE:             return MOD_PURPOSE_INTERFACE;

        default: return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function attendees_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return array();
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
    return array('view','view all');
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
    return array('update', 'add');
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

    $options = array();
    $options['printintro']       = $data->printintro;
    $options['timecard']         = $data->timecard;
    $options['autosignout']      = $data->autosignout;
    $options['defaultview']      = $data->defaultview;
    $options['showroster']       = $data->showroster;
    $options['lockview']         = $data->lockview;
    $options['kioskmode']        = $data->kioskmode;
    $options['iplock']           = $data->iplock;
    $options['searchfields']     = $data->searchfields;

    $data->displayoptions = serialize($options);
    $data->id = $DB->insert_record('attendees', $data);

    // we need to use context now, so we need to make sure all needed info is already in db
    $DB->set_field('course_modules', 'instance', $data->id, array('id'=>$cmid));

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'attendees', $data->id, $completiontimeexpected);

    return $data->id;
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

    $options = array();
    $options['printintro']       = $data->printintro;
    $options['timecard']         = $data->timecard;
    $options['autosignout']      = $data->autosignout;
    $options['defaultview']      = $data->defaultview;
    $options['showroster']       = $data->showroster;
    $options['lockview']         = $data->lockview;
    $options['kioskmode']        = $data->kioskmode;
    $options['iplock']           = $data->iplock;
    $options['searchfields']     = $data->searchfields;

    $data->displayoptions = serialize($options);

    $DB->update_record('attendees', $data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'attendees', $data->id, $completiontimeexpected);

    return true;
}

/**
 * Delete attendees instance.
 * @param int $id
 * @return bool true
 */
function attendees_delete_instance($id) {
    global $DB;

    if (!$attendees = $DB->get_record('attendees', array('id' => $id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('attendees', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'attendees', $id, null);

    // note: all context files are deleted automatically

    $DB->delete_records('attendees', array('id' => $attendees->id));
    $DB->delete_records('attendees_timecard', array('aid' => $attendees->id));

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

    if (!$viewrosters && !$attendees->showroster &&
        !$attendees->timecard && !$attendees->kioskmode) {
        $cm->set_no_view_link();
    }
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@link course_modinfo::get_array_of_activities()}
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info Info to customise main attendees display
 */
function attendees_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if (!$attendees = $DB->get_record('attendees', array('id'=>$coursemodule->instance),
            'id, name, intro, introformat')) {
        return NULL;
    }

    $info = new cached_cm_info();
    $info->name = $attendees->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('attendees', $attendees, $coursemodule->id, false);
    }

    if ($attendees->display != RESOURCELIB_DISPLAY_POPUP) {
        return $info;
    }

    $fullurl = "$CFG->wwwroot/mod/attendees/view.php?id=$coursemodule->id";

    $wh = "toolbar=no,location=no,menubar=no,copyhistory=no,status=no,directories=no,scrollbars=yes,resizable=yes";
    $info->onclick = "window.open('$fullurl', '', '$wh'); return false;";

    return $info;
}

/**
 * Return a list of attendees types
 * @param string $pagetype current attendees type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function attendees_attendees_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_attendeestype = array('mod-attendees-*'=>get_string('attendees-mod-attendees-x', 'attendees'));
    return $module_attendeestype;
}

/**
 * Export attendees resource contents
 *
 * @return array of file content
 */
function attendees_export_contents($cm, $baseurl) {
    global $CFG, $DB;
    $contents = array();
    $context = context_module::instance($cm->id);

    $attendees = $DB->get_record('attendees', array('id'=>$cm->instance), '*', MUST_EXIST);

    return $contents;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $attendees       attendees object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function attendees_view($attendees, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $attendees->id
    );

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
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function attendees_check_updates_since(cm_info $cm, $from, $filter = array()) {
    $updates = course_check_module_updates_since($cm, $from, array('content'), $filter);
    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_attendees_core_calendar_provide_event_action(calendar_event $event,
                                                      \core_calendar\action_factory $factory, $userid = 0) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['attendees'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/attendees/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

function attendees_get_ui($cm, $attendees, $course, $tab = 'all') {
    global $USER;
    $context = context_module::instance($cm->id);
    $viewrosters = has_capability('mod/attendees:viewrosters', $context);

    $content = "";
    if (!$viewrosters && is_enrolled($context, $USER, 'mod/attendees:signinout', true) && $attendees->timecard) {
        $content .= attendees_sign_inout_button($cm, $tab);
    }

    // GROUP MODE
    if ($groupmode = groups_get_activity_groupmode($cm)) {
        $url = new moodle_url('/mod/attendees/view.php', ['id' => $cm->id]);
        groups_print_activity_menu($cm, $url);

        $groupid = groups_get_activity_group($cm);
    }

    if (!$groupmode || (!$cm->groupingid && !$groupid)) { // All users.
        $users = get_enrolled_users($context, 'mod/attendees:signinout', 0, 'u.*', 'lastname ASC');
    } else if ($cm->groupingid && !$groupid) { // Show all users in grouping groups.
        $users = groups_get_grouping_members($cm->groupingid, 'u.*', 'lastname ASC');
    } else if ($groupid) { // Only show users in group.
        $users = get_enrolled_users($context, 'mod/attendees:signinout', $groupid, 'u.*', 'lastname ASC');
    }

    // Sort for active tab.
    if ($tab !== "all") {
        if ($tab == "onlyin") {
            $users = array_filter($users, function($user) use($cm) {
                return attendees_is_active($user, $cm->instance);
            });
        } else {
            $users = array_filter($users, function($user) use($cm) {
                return !attendees_is_active($user, $cm->instance);
            });
        }
    }


    // DATA EXPORT LINK

    if ($viewrosters || $attendees->showroster) {
        if (has_capability('mod/attendees:signinout', $context)) {
            if (!$attendees->lockview && !$attendees->kioskmode) {
                $content .= attendees_roster_tabs($cm, $tab);
            }
            $content .= attendees_roster_view($cm, $users, $tab);
        } else {
            $content .= attendees_roster_view($cm, $users, $tab);
        }
    }
    return $content;
}

function attendees_roster_tabs($cm, $tab) {
    global $CFG;
    $all = $onlyin = $onlyout = "";
    $$tab = 'active active_tree_node';
    $url = "$CFG->wwwroot/mod/attendees/view.php?id=$cm->id&tab=";

    return '<div class="attendees_tabs secondary-navigation d-print-none">
                <nav class="moremenu navigation observed" style="margin: 0">
                    <ul class="nav more-nav nav-tabs">
                        <li class="nav-item">
                            <a href="'.$url.'all" class="nav-link '.$all.'">All Users</a>
                        </li>
                        <li class="nav-item">
                            <a href="'.$url.'onlyin" class="nav-link '.$onlyin.'">Active Users</a>
                        </li>
                        <li class="nav-item">
                            <a href="'.$url.'onlyout" class="nav-link '.$onlyout.'">Inactive Users</a>
                        </li>
                    </ul>
                </nav>
            </div>';
}

function attendees_sign_inout_button($cm, $tab) {
    global $CFG, $USER, $DB, $OUTPUT;
    $user = $DB->get_record('user', array('id' => $USER->id), '*', MUST_EXIST);
    $url = "$CFG->wwwroot/mod/attendees/action.php?id=$cm->id&tab=$tab";
    if (attendees_is_active($user, $cm->instance)) { 
        $text = get_string("signout", "attendees");
        $inorout = $OUTPUT->pix_icon('a/logout', $text , 'moodle') . " $text";
    } else {
        $text = get_string("signin", "attendees");
        $inorout = $OUTPUT->pix_icon('withoutkey', $text , 'enrol_self') . " $text";
    }

    return "<div style='text-align:center'><a class='attendees_signinout_button' href='$url' alt='$text '>$inorout</a></div>";
}

function attendees_signinout($attendees, $userid) {
    global $DB;
    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
    $time = new DateTime("now", core_date::get_server_timezone_object());
    $timestamp = $time->getTimestamp();

    $timecard = new stdClass();
    $timecard->userid = $user->id;
    $timecard->aid = $attendees->id;
    $timecard->timelog = $timestamp;
    $timecard->ip = getremoteaddr();

    if (attendees_is_active($user, $attendees->id)) {
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

function attendees_current_status($cm, $user) {
    if (attendees_is_active($user, $cm->instance)) {
        return "in";
    } else {
        return "out";
    }
}

function attendees_is_active($user, $aid) {
    global $DB;

    $attendees = $DB->get_record('attendees', ['id' => $aid]);
    $iplock = "";
    if ($attendees->iplock) {
        $ip = getremoteaddr();
        $iplock = "AND ip = '$ip' ";
    }

    $lastout = $DB->get_record_sql(
        'SELECT * FROM {attendees_timecard} WHERE aid = ? AND event = ? AND userid = ? ' .$iplock. 'ORDER BY timelog DESC LIMIT 1',
        [$aid, 'out', $user->id]
    );
    $lastin = $DB->get_record_sql(
        'SELECT * FROM {attendees_timecard} WHERE aid = ? AND event = ? AND userid = ?  ' .$iplock. 'ORDER BY timelog DESC LIMIT 1',
        [$aid, 'in', $user->id]
    );
    $today = attendees_get_today();

    if (!empty($lastin) || !empty($lastout)) {
        if ($attendees->autosignout) { // Auto signed out at the end of the day.
            if (!empty($lastout) && !empty($lastin) &&
                $lastout->timelog > $lastin->timelog && $lastout->timelog > $today) { // have signed out today
                return false;
            } elseif (!empty($lastout) && !empty($lastin) &&
                      $lastin->timelog > $lastout->timelog && $today > $lastin->timelog) { // haven't signed in today
                return false;
            } elseif (!empty($lastout) && !empty($lastin) &&
                      $lastout->timelog > $lastin->timelog && $today > $lastout->timelog) { // new day
                return false;
            } elseif (empty($lastin)) { // have never signed in
                return false;
            }
            return true;
        } else { // No autosignout.
            if (!empty($lastout) && !empty($lastin) && 
                $lastout->timelog > $lastin->timelog) { // last action was a sign out
                return false;
            } elseif (empty($lastin)) { // have never signed in
                return false;
            }
            return true;
        }
    }
    return false;
}

function attendees_get_today(){
    global $CFG;
    $dateinmytimezone = new DateTime("now", core_date::get_server_timezone_object());
    $UTCdate = new DateTime($dateinmytimezone->format("m/d/Y"), new DateTimeZone("UTC"));
    return $UTCdate->getTimestamp();
}

function attendees_lookup($attendees, $code) { // Only in Kiosk Mode.
    $cm = get_coursemodule_from_instance('attendees', $attendees->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    $searchfields = (array) unserialize_array($attendees->searchfields);
    if (empty($searchfields)) { // If empty, search all fields.
        $searchfields = array('idnumber' => get_string("idnumber"), 
                              'email' => get_string("email"), 
                              'username' => get_string("username"), 
                              'phone1' => get_string("phone1"), 
                              'phone2' => get_string("phone2")); 
    }

    // Get all possible users.
    $users = get_enrolled_users($context, 'mod/attendees:signinout', 0, 'u.*', 'lastname ASC');
    $founduser = array();
    foreach ($users as $user) {
        foreach ($searchfields as $field) {
            echo $user->$field . " = $code ?<br />";
            if ($user->$field == $code) {
                $founduser[] = $user;
                break;
            }
        }
    }

    if (count($founduser) !== 1) { // none or more than 1 match.
        return get_string("codenotfound", "attendees");
    } else { // exactly 1 matching user.
        return $founduser[0]->id;
    }

}

function attendees_roster_view($cm, $users, $tab) {
    global $CFG, $OUTPUT, $DB;
    require_once($CFG->libdir.'/filelib.php');

    $context = context_module::instance($cm->id);
    $signinoutothers = has_capability('mod/attendees:signinoutothers', $context);
    $attendees = $DB->get_record('attendees', ['id' => $cm->instance]);

    $url = "$CFG->wwwroot/mod/attendees/action.php?id=$cm->id&tab=$tab";
    $output = "";

    if ($attendees->kioskmode) { // Add search mode for kiosk.
        $output .= '<div class="attendees_usersearch">
                        <form method="get" action="' . $url . '">
                            <input type="hidden" name="id" id="id" value="' . $cm->id . '" />
                            <input type="hidden" name="tab" id="tab" value="' . $tab . '" />
                            Username / ID <input type="password" name="code" id="code" onblur="this.focus()" autofocus />
                            <input type="submit" value="' . get_string("signinout", "attendees") . '" />
                        </form>
                    </div>';
    }

    foreach ($users as $user) {
        $status = attendees_current_status($cm, $user);
        $output .= '<div class="attendees_userblock attendees_status_'. $status . '">';

        if ($attendees->timecard && $signinoutothers && !$attendees->kioskmode) { // Only show icons if timecard is enabled and has permissions.
            $output .= '<a class="attendees_otherinout_button" href="' . $url . "&userid=$user->id" . '" alt="' . get_string("signinout", "attendees") . '">
                            ' . $OUTPUT->pix_icon('a/logout', get_string("signout", "attendees"), 'moodle') . '
                            ' . $OUTPUT->pix_icon('withoutkey', get_string("signin", "attendees"), 'enrol_self') . '
                        </a>';
        }

        $options = array( 
            'size' => '100', // size of image
            'link' => !$attendees->kioskmode, // make image clickable - the link leads to user profile
            'alttext' => true, // add image alt attribute
            'class' => "userpicture", // image class attribute
            'visibletoscreenreaders' => false,
        );
        $userpic = $OUTPUT->user_picture($user, $options);
        $output .= $userpic;
        $output .= '<div class="attendees_name"> ' . $user->firstname . ' ' . $user->lastname . '</div>';
        $output .= attendees_list_user_groups($cm, $attendees, $user->id);
        $output .= '</div>';
    }
    return $output;
}

function attendees_list_user_groups($cm, $attendees, $userid) : string {
    $grouplist = "";
    if ($attendees->showgroups) {
        $groupings = groups_get_user_groups($cm->course, $userid);
        foreach($groupings[0] as $group) {
            $grouplist .= "<div>" . groups_get_group_name($group) . "</div>";
        }
    }

    return '<div class="attendees_groups">' . $grouplist . '</div>';
}