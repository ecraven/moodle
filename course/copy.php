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
 * Copy a course.
 *
 * @package core_course
 * @copyright 2002 onwards Martin Dougiamas (http://dougiamas.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once('copy_form.php');

$id = required_param('id', PARAM_INT); // Course ID.
$categoryid = optional_param('category', 0, PARAM_INT); // Course category - can be changed in edit form.
$copy = optional_param('makecopy', '', PARAM_ALPHANUM); // Confirmation hash.
$returnto = optional_param('returnto', 0, PARAM_ALPHANUM); // Generic navigation return page switch.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL); // A return URL. returnto must also be set to 'url'.

if ($returnto === 'url' && confirm_sesskey() && $returnurl) {
    // If returnto is 'url' then $returnurl may be used as the destination to return to after saving or cancelling.
    // Sesskey must be specified, and would be set by the form anyway.
    $returnurl = new moodle_url($returnurl);
} else {
    if (!empty($id)) {
        $returnurl = new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $id));
    } else {
        $returnurl = new moodle_url($CFG->wwwroot . '/course/');
    }
    if ($returnto !== 0) {
        switch ($returnto) {
            case 'category':
                $returnurl = new moodle_url($CFG->wwwroot . '/course/index.php', array('categoryid' => $categoryid));
                break;
            case 'catmanage':
                $returnurl = new moodle_url($CFG->wwwroot . '/course/management.php', array('categoryid' => $categoryid));
                break;
            case 'topcatmanage':
                $returnurl = new moodle_url($CFG->wwwroot . '/course/management.php');
                break;
            case 'topcat':
                $returnurl = new moodle_url($CFG->wwwroot . '/course/');
                break;
            case 'pending':
                $returnurl = new moodle_url($CFG->wwwroot . '/course/pending.php');
                break;
        }
    }
}

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);

require_login();

$categorycontext = context_coursecat::instance($course->category);
$PAGE->set_url('/course/copy.php', array('id' => $id));

// Basic access control checks.
if ($id) {
    // Login to the course and retrieve also all fields defined by course format.
    $course = get_course($id);
    require_login($course);
    $course = course_get_format($course)->get_course();

    $category = $DB->get_record('course_categories', array('id' => $course->category), '*', MUST_EXIST);
    $coursecontext = context_course::instance($course->id);
    require_capability('moodle/course:update', $coursecontext);

}
$PAGE->set_context($categorycontext);
$PAGE->set_pagelayout('admin');
navigation_node::override_active_url(new moodle_url('/course/management.php', array('categoryid' => $course->category)));

$courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
$coursefullname = format_string($course->fullname, true, array('context' => $coursecontext));
$categoryurl = new moodle_url('/course/management.php', array('categoryid' => $course->category));

$strcopycheck = get_string("copycheck", "", $courseshortname);
$continueurl = new moodle_url('/course/copy.php', array('id' => $course->id, 'delete' => md5($course->timemodified)));

// First create the form.
$args = array(
    'course' => $course,
    'category' => $category,
    'returnto' => $returnto,
    'returnurl' => $returnurl
);
$copyform = new course_copy_form(null, $args);
if ($copyform->is_cancelled()) {
    // The form has been cancelled, take them back to what ever the return to is.
    redirect($returnurl);
} else if ($data = $copyform->get_data()) {
    // Process data if submitted.

    // The cloning options. We are going to un-enrol all users and then re-enrol the enabled roles for manual enrolments.
    $options = array(
        array('name' => 'users', 'value' => 0)
    );

    $copiedcourse = core_course_external::duplicate_course($id, $data->fullname, $data->shortname, $data->category, $data->visible,
            $options);

    // Get the context of the newly created course.
    $context = context_course::instance($copiedcourse['id'], MUST_EXIST);

    $course = $DB->get_record('course', array('id' => $copiedcourse['id']), '*', MUST_EXIST);
    // Set start date.
    if (isset($data->startdate)) {
        $course->startdate = $data->startdate;
    }
    // Set end date.
    if (isset($data->enddate)) {
        $course->enddate = $data->enddate;
    }
    // Set short name.
    if (isset($data->idnumber)) {
        $course->idnumber = $data->idnumber;
    }
    $DB->update_record('course', $course);

    if (isset($data->keeproles)) {
        foreach ($data->keeproles as $role) {
            course_copy_manual_course_enrolments($copiedcourse['id'], $id, $role);
        }
    }

    if (!empty($CFG->creatornewroleid) and ! is_viewing($context, null, 'moodle/role:assign') and ! is_enrolled($context, null,
            'moodle/role:assign')) {
        // Deal with course creators - enrol them internally with default role.
        enrol_try_internal_enrol($copiedcourse['id'], $USER->id, $CFG->creatornewroleid);
    }

    // The URL to take them to if they chose save and display.
    $courseurl = new moodle_url('/course/view.php', array('id' => $copiedcourse['id']));

    // If they choose to save and display, and they are not enrolled take them to the enrolments page instead.
    if (!is_enrolled($context) && isset($data->copyanddisplay)) {
        // Redirect to manual enrolment page if possible.
        $instances = enrol_get_instances($copiedcourse['id'], true);
        foreach ($instances as $instance) {
            if ($plugin = enrol_get_plugin($instance->enrol)) {
                if ($plugin->get_manual_enrol_link($instance)) {
                    // We know that the ajax enrol UI will have an option to enrol.
                    $courseurl = new moodle_url('/enrol/users.php', array('id' => $copiedcourse['id'], 'newcourse' => 1));
                    break;
                }
            }
        }
    }

    if (isset($data->copyanddisplay)) {
        // Redirect user to newly the created course.
        redirect($courseurl);
    } else {
        // Copy and return. Take them back to wherever.
        redirect($returnurl);
    }
}

// Print the form.

$site = get_site();

$strcopycourse = get_string("copycourse");

$pagedesc = $strcopycourse;
$title = "$site->shortname: $strcopycourse";
$fullname = $site->fullname;
$PAGE->navbar->add($pagedesc);

$PAGE->set_title("$SITE->shortname: $strcopycheck");
$PAGE->set_heading($SITE->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($pagedesc);

$copyform->display();

echo $OUTPUT->footer();
exit;
