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
 * the update user class for panopto
 *
 * @package block_panopto
 * @copyright Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu),
 * Skylar Kelty (S.Kelty@kent.ac.uk)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto\task;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');

/**
 * Panopto "update user" task.
 * @copyright Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu),
 * Skylar Kelty (S.Kelty@kent.ac.uk)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_user extends \core\task\adhoc_task {

    /**
     * the the parent component for this class
     */
    public function get_component() {
        return 'block_panopto';
    }

    /**
     * the main execution function of the class
     */
    public function execute() {
        global $DB;

        $eventdata = (array) $this->get_custom_data();

        $courseid = $eventdata['courseid'];
        $eventtype = $eventdata['eventtype'];

        $panopto = new \panopto_data($courseid);
        $enrollmentinfo = $this->get_info_for_enrollment_change($panopto, $eventdata['relateduserid'], $eventdata['contextid']);
        $targetrole = $enrollmentinfo['role'];
        $targetuserkey = $enrollmentinfo['userkey'];

        switch ($eventtype) {
            case 'enroll_add':
                $panopto->add_course_user($targetrole, $targetuserkey);
                break;

            case 'enroll_remove':
                $panopto->remove_course_user($targetrole, $targetuserkey);
                break;

            case 'role':
                $panopto->change_user_role($targetrole, $targetuserkey);
                break;
        }
        // Need to reprovision the course and it's imports for enrollment/role changes to take effect on panopto's side.
        $courseimports = \panopto_data::get_import_list($courseid);
        foreach ($courseimports as $importedcourse) {
            $importedpanopto = new \panopto_data($importedcourse);

            $importprovisioninginfo = $importedpanopto->get_provisioning_info();
            $importedpanopto->provision_course($importprovisioninginfo);
        }

        $provisioninginfo = $panopto->get_provisioning_info();
        $provisioneddata = $panopto->provision_course($provisioninginfo);
    }

    /**
     * Return the correct role for a user, given a context.
     *
     * @param int $contextid
     * @param int $userid
     */
    private function get_role_from_context($contextid, $userid) {
        $context = \context::instance_by_id($contextid);
        $role = 'Viewer';
        if (has_capability('block/panopto:provision_aspublisher', $context, $userid)) {
            if (has_capability('block/panopto:provision_asteacher', $context, $userid)) {
                $role = 'Creator/Publisher';
            } else {
                $role = 'Publisher';
            }
        } else if (has_capability('block/panopto:provision_asteacher', $context, $userid)) {
            $role = 'Creator';
        }
        return $role;
    }

    /**
     * Return user info for this event.
     *
     * @param object $panopto
     * @param int $relateduserid
     * @param int $contextid
     */
    private function get_info_for_enrollment_change($panopto, $relateduserid, $contextid) {

        // DB userkey is "[instancename]\\[username]". Get username and use it to create key.
        $user = get_complete_user_data('id', $relateduserid);
        $username = $user->username;
        $userkey = $panopto->panopto_decorate_username($username);

        // Get contextID to determine user's role.
        $role = $this->get_role_from_context($contextid, $relateduserid);

        return array(
            'role' => $role,
            'userkey' => $userkey
        );
    }

}
