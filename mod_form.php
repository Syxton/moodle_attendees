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
 * Attendees configuration form
 *
 * @package mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->libdir.'/filelib.php');

class mod_attendees_mod_form extends moodleform_mod {
    function definition() {
        global $CFG, $DB;

        $mform = $this->_form;

        $config = get_config('attendees');

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'appearancehdr', get_string('appearance'));

        // Number of attempts.
        $defaultviewoptions = [
            'all' => get_string('all'),
            'onlyin' => get_string('onlyin', 'attendees'),
            'onlyout' => get_string('onlyout', 'attendees')
        ];
        $mform->addElement('select', 'defaultview', get_string('rosterview', 'attendees'), $defaultviewoptions);
        
        $mform->addElement('advcheckbox', 'lockview', get_string('lockview', 'attendees'));
        $mform->setDefault('lockview', $config->lockview);

        $mform->addElement('advcheckbox', 'kioskmode', get_string('kioskmode', 'attendees'));
        $mform->setDefault('kioskmode', $config->kioskmode);

        $mform->addElement('advcheckbox', 'showroster', get_string('showroster', 'attendees'));
        $mform->setDefault('showroster', $config->showroster);

        $mform->addElement('header', 'timecardhdr', get_string('timecard', 'attendees'));
        $mform->addElement('advcheckbox', 'timecard', get_string('timecardenabled', 'attendees'));
        $mform->setDefault('timecard', $config->timecard);

        $mform->addElement('advcheckbox', 'autosignout', get_string('autosignout', 'attendees'));
        $mform->setDefault('autosignout', $config->autosignout);

        //-------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------
        $this->add_action_buttons();
    }

    /**
     * Enforce defaults here.
     *
     * @param array $defaultvalues Form defaults
     * @return void
     **/
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($defaultvalues['displayoptions'])) {
            $displayoptions = (array) unserialize_array($defaultvalues['displayoptions']);
            if (isset($displayoptions['printintro'])) {
                $defaultvalues['printintro'] = $displayoptions['printintro'];
            }
        }
    }
}

