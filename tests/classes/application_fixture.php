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

namespace certificateelement_enrolapply\tests;

use core_customfield\field_controller;
use enrol_apply\customfield\application_handler;
use enrol_apply\local\answers as application_answers;
use enrol_apply\local\submission;

/**
 * Builds enrolment methods, their questions and applications for the tests of this plugin.
 *
 * @package    certificateelement_enrolapply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait application_fixture {
    /**
     * Add an enrol_apply method to a course.
     *
     * @param int $courseid The course.
     * @return int Row id in enrol.
     */
    protected function add_method(int $courseid): int {
        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $plugin = enrol_get_plugin('apply');
        return (int) $plugin->add_instance(get_course($courseid), $plugin->get_instance_defaults());
    }

    /**
     * Create a text question in a custom field category.
     *
     * @param string $shortname The question's short name.
     * @param string $component Component of the category: enrol_apply for a method's own questions.
     * @param string $area Area of the category.
     * @param int $itemid Item of the category: the enrol id for a method's own questions.
     * @return field_controller
     */
    protected function add_question(string $shortname, string $component, string $area, int $itemid): field_controller {
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category(['component' => $component, 'area' => $area, 'itemid' => $itemid]);
        return $generator->create_field([
            'categoryid' => $category->get('id'),
            'type' => 'text',
            'shortname' => $shortname,
            'name' => ucfirst($shortname),
        ]);
    }

    /**
     * Create a question of an enrolment method.
     *
     * @param int $enrolid The method.
     * @param string $shortname The question's short name.
     * @return field_controller
     */
    protected function add_method_question(int $enrolid, string $shortname): field_controller {
        return $this->add_question($shortname, 'enrol_apply', application_handler::AREA, $enrolid);
    }

    /**
     * Record an application with its frozen answers, as enrol_apply leaves it after a decision.
     *
     * @param int $courseid The course applied for.
     * @param int $userid The applicant.
     * @param int $enrolid The method applied through.
     * @param array $answers Field id => frozen answer; any other string key is stored as it is.
     * @param int $status One of submission's STATUS_ constants.
     * @param int $timecreated When the application was submitted.
     * @return int Row id in enrol_apply_submission.
     */
    protected function add_application(
        int $courseid,
        int $userid,
        int $enrolid,
        array $answers,
        int $status = submission::STATUS_APPROVED,
        int $timecreated = 1700000000
    ): int {
        global $DB;

        $id = (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'enrolid' => $enrolid,
            'userenrolmentid' => 0,
            'comment' => '',
            'status' => $status,
            'outcomemessage' => '',
            'timecreated' => $timecreated,
            'timedecided' => $status === submission::STATUS_PENDING ? 0 : $timecreated + 60,
            'decidedby' => 0,
        ]);

        $entries = [];
        foreach ($answers as $key => $value) {
            $entries[] = [
                'key' => is_int($key) ? application_answers::key($key) : $key,
                'label' => 'Question',
                'value' => $value,
            ];
        }
        submission::write_answers($id, $entries);

        return $id;
    }
}
