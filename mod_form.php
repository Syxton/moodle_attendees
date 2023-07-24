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

        $mform->addElement('advcheckbox', 'showroster', get_string('showroster', 'attendees'));
        $mform->setDefault('showroster', $config->showroster);

        $mform->addElement('advcheckbox', 'showgroups', get_string('showgroups', 'attendees'));
        $mform->setDefault('showgroups', $config->showgroups);

        $mform->addElement('header', 'kioskmodedhdr', get_string('kioskmode', 'attendees'));
        $mform->addElement('advcheckbox', 'kioskmode', get_string('kioskmode', 'attendees'));
        $mform->setDefault('kioskmode', $config->kioskmode);
                                                       
        $searchfields = array('idnumber' => get_string("idnumber"), 
                              'email' => get_string("email"), 
                              'username' => get_string("username"), 
                              'phone1' => get_string("phone1"), 
                              'phone2' => get_string("phone2"));                                                                                                                           
        $options = array(                                                                                                           
            'multiple' => true,                                                  
            'noselectionstring' => get_string('allareas', 'search'),                                                                
        );         
        $mform->addElement('autocomplete', 'searchfields', get_string('searcharea', 'search'), $searchfields, $options);
        $mform->setDefault('searchfields', $defaultvalues->searchfields);

        $mform->addElement('header', 'timecardhdr', get_string('timecard', 'attendees'));
        $mform->addElement('advcheckbox', 'timecard', get_string('timecardenabled', 'attendees'));
        $mform->setDefault('timecard', $config->timecard);

        $mform->addElement('advcheckbox', 'autosignout', get_string('autosignout', 'attendees'));
        $mform->setDefault('autosignout', $config->autosignout);

        $mform->addElement('advcheckbox', 'iplock', get_string('iplock', 'attendees'));
        $mform->setDefault('iplock', $config->iplock);

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
        global $DB;

        $ctx = null;
        if ($this->current && $this->current->coursemodule) {
            $cm = get_coursemodule_from_instance('attendees', $this->current->id, 0, false, MUST_EXIST);
            $ctx = context_module::instance($cm->id);

            $attendees = $DB->get_record('attendees', ['id' => $cm->instance]);
            $defaultvalues['searchfields'] = (array) unserialize_array($attendees->searchfields);
        }

        if (!empty($defaultvalues['displayoptions'])) {
            $displayoptions = (array) unserialize_array($defaultvalues['displayoptions']);
            if (isset($displayoptions['printintro'])) {
                $defaultvalues['printintro'] = $displayoptions['printintro'];
            }
        }
    }
}

