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
 * This file defines the quiz handout report class.
 *
 * @package   quiz_handout
 * @copyright 2018 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_options.php');

/**
 * Quiz report subclass for the handout report.
 *
 * @copyright 2018 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_handout_report extends quiz_attempts_report {

    /**
     * This function calls the report's display.
     * @param object $quiz this quiz.
     * @param object $cm the course module for this quiz.
     * @param stdClass $course The course we are in.
     * @return bool
     * @throws coding_exception
     */
    public function display($quiz, $cm, $course) {
        global $DB, $OUTPUT, $PAGE;

        $options = new mod_quiz_attempts_report_options('handout', $quiz, $cm, $course);

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $filename = quiz_report_download_filename(get_string('handoutfilename', 'quiz_handout'),
                $courseshortname, $quiz->name);

        $hasstudents = true;

        $hasquestions = quiz_has_questions($quiz->id);

        $this->print_header_and_tabs($cm, $course, $quiz, $this->mode);

        // Print the display options.
        return true;
    }

    /**
     * Get the slots of ALL questions (including descriptions) in this quiz, in order.
     * @param object $quiz the quiz.
     * @return array of slot => $question object with fields
     *      ->slot, ->id, ->maxmark, ->length, ->number.
     */
    public function quiz_report_get_all_questions($quiz) {
        global $DB;

        $qsbyslot = $DB->get_records_sql("
            SELECT slot.slot,
                   q.id,
                   q.length,
                   slot.maxmark

              FROM {question} q
              JOIN {quiz_slots} slot ON slot.questionid = q.id

             WHERE slot.quizid = ?
               AND q.length > -1

          ORDER BY slot.slot", array($quiz->id));

        $number = 1;
        foreach ($qsbyslot as $question) {
            $question->number = $number;
            $number += $question->length;
        }

        return $qsbyslot;
    }
}
