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
 * This file defines the quiz solution report class.
 *
 * @package   quiz_solution
 * @copyright 2018 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/solution/locallib.php');

/**
 * Quiz report subclass for the solution report.
 *
 * @copyright 2018 Luca Bösch <luca.boesch@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_solution_report extends quiz_attempts_report {

    /** @var array non printable question types. */
    public $nonprintableqtypes = array("coderunner", "ddimageortext", "ddmarker", "pmatch", "regexp");

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

        raise_memory_limit(MEMORY_HUGE);

        // The stuff to display.
        $todisplay = "";

        $options = new mod_quiz_attempts_report_options('solution', $quiz, $cm, $course);

        // Work out the display options.
        $download = optional_param('download', -1, PARAM_INT);

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_module::instance($cm->id)));
        $filename = quiz_report_download_filename(get_string('solutionfilename', 'quiz_solution'),
                $courseshortname, $quiz->name) . ".doc";

        require_login($course->id, false, $cm);

        $isteacher = has_capability('mod/quiz:preview', context_module::instance($cm->id));

        $popup = $isteacher ? 0 : $quiz->popup; // Controls whether this is shown in a javascript-protected window.

        $todisplay .= $this->writesolution($quiz, $cm, true);

        $hasstudents = true;

        $hasquestions = quiz_has_questions($quiz->id);

        if ($download == 1) {
            /*
             * @var string export template with Word-compatible CSS style definitions
             */
            $wordfiletemplate = 'wordfiletemplate.html';

            /*
             * @var string Stylesheet to export XHTML into Word-compatible XHTML
             */
            $exportstylesheet = 'xhtml2wordpass2.xsl';

            // XHTML template for Word file CSS styles formatting.
            $htmltemplatefilepath = __DIR__ . "/" . $wordfiletemplate;
            $stylesheet = __DIR__ . "/" . $exportstylesheet;

            // Read the title and introduction into a string, embedding images.
            $htmloutput = '<p class="MsoTitle">' . $this->get_quiz_title($quiz) . "</p>\n";
            $htmloutput .= '<div class="chapter" id="intro">' . $this->get_name_table();
            $htmloutput .= "</div>\n";

            $htmloutput .= "<div class=\"chapter\">";
            $htmloutput .= $todisplay;
            $htmloutput .= "</div>\n";

            $htmloutput = str_replace ('<input type="checkbox" />',
                '<span style="font-size: 15pt; font-family: Arial;">&#x25a1;</span>', $htmloutput);
            $htmloutput = str_replace ('<input type="radio" />',
                '<span style="font-size: 15pt; font-family: Arial;">&#x25cb;</span>', $htmloutput);
            $htmloutput = preg_replace('/<input type="text" value="(.+?)" size="(.+?)" style="border: 1px dashed #000000; ' .
                'height: 24px;"\/>/',
                '<span style ="border: 1px dashed #000000; padding-left: 0.5em; padding-right: 0.5em; height: 24px">$1</span>',
                $htmloutput);

            file_put_contents('/Users/luca/Desktop/log0.txt', $htmloutput);
            $docxcontent = solution_wordimport_export($htmloutput);
            send_file($docxcontent, $filename, 10, 0, true, array('filename' => $filename));
            die;
        } else {
            $this->print_header_and_tabs($cm, $course, $quiz, $this->mode);
            echo "<div role='navigation'><a href='" . $PAGE->url . "&download=1' />" .
                get_string('solutiondownload', 'quiz_solution') . "</a><br/>&#160;</div>";
            echo $this->get_name_table();
            echo $todisplay;
        }
        return true;
    }

    /**
     * Get the slots of ALL questions (including descriptions) in this quiz, in order.
     * @param object $quiz the quiz.
     * @return array of slot => $question object with fields
     *      ->slot, ->id, ->maxmark, ->length, ->number.
     */
    public function quiz_report_get_all_question_slots($quiz) {
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

    /**
     * Write the solution.
     *
     * @param object $quiz this quiz.
     * @param object $cm the course module for this quiz.
     * @return string the solution to display or print to doc.
     * @throws coding_exception
     */
    public function writesolution($quiz, $cm) {
        global $OUTPUT, $quiz, $displayoptions;

        $questionheaders = array();
        $solutions = false;
        $questionslots = $this->quiz_report_get_all_question_slots($quiz);

        /* bsl3 following code from /mod/quiz/review.php */
        /* rlm1 collect generated HTML */

        $fulltext = "";

        $questionpointer = 0;
        $quizobj = quiz::create($quiz->id);
        $structure = $quizobj->get_structure();

        foreach ($questionslots as $qs) {
            // Do load_question only if the according qtype is installed.
            if ($structure->get_question_type_for_slot($qs->slot) === "missingtype") {
                continue;
            } else {
                $question = question_bank::load_question($qs->id);
                $questiondata = question_bank::load_question_data($qs->id);
                $questionheaders[] = get_string('question', 'quiz')." ". $qs->number . " (" .
                    number_format($qs->maxmark, 2) . " ".get_string('marks', 'quiz').")";

                $text = question_rewrite_question_preview_urls($this->formatquestiondata($questiondata, true), $question->id,
                    $question->contextid, 'question', 'questiontext', $question->id,
                    $cm->id, 'quiz_solution');

                if (trim($text) != '') {
                    // Rlm1 Check if element is description, not question ...
                    if ($questiondata->qtype == "description") {
                        $fulltext .= "<h1>" . $questiondata->name . "</h1><p>" . $text . "</p>";
                    } else {
                        $fulltext .= "<h2>" . $questionheaders[$questionpointer++] . "</h2>";

                        $fulltext .= $OUTPUT->box(format_text($text, $question->questiontextformat, array('noclean' => true,
                            'para' => false, 'overflowdiv' => true)), 'questiontext boxaligncenter generalbox');

                        if (isset($question->responseformat)) {
                            /* white fields for editor answers with lines equal to responsefieldlines */
                            if ($question->responseformat == "editor" OR $question->responseformat == "monospaced") {
                                if ($solutions) { // Solution.
                                    $boxtext = get_string('singlesolution', 'quiz_solution') . ":<br />\n";
                                    // This are the graderinfo informations, as there is no such thing as solution
                                    // to the essay question type.
                                    $boxtext .= $question->graderinfo;
                                } else { /* solution. */
                                    $boxtext = "";
                                    if ($question->responsefieldlines != 0) {
                                        for ($i = 0; $i < $question->responsefieldlines; $i++) {
                                            $boxtext .= "<br />";
                                        }
                                    }
                                }
                                $fulltext .= "\n";
                                $fulltext .= "<div class=\"box\" style=\"border: 1px dashed #000000; padding: 10px; ";
                                $fulltext .= "margin-bottom: 15px;\">$boxtext</div>";
                            }
                        }
                    }
                }
            }
        }
        $displayoptions = mod_quiz_display_options::make_from_quiz($quiz, mod_quiz_display_options::DURING);

        // Really, PHP should not need this hint, but without this, we just run out of memory.
        $quba = null;
        $transaction = null;
        gc_collect_cycles();

        $fulltext .= "</body></html>";

        return ($fulltext);
    }

    /**
     * Format the question data.
     *
     * @param object $questiondata the data defining a question.
     * @param bool $solutions whether to show the solutions
     * @return null|string|string[]
     * @throws coding_exception
     */
    public function formatquestiondata($questiondata, $solutions = false) {
        global $questiontext, $replacearray, $annotation, $annotationnumbering, $annotationsarray, $fieldsarray;
        // Initialise the annotation numbering index.
        $annotationnumbering = 1;
        // Empty the annotation string.
        $annotation = "";
        // Empty the question text string.
        $questiontext = "";
        // Empty the replacearray.
        $replacearray = array();
        // Empty the annotationsarray.
        // The annotationsarray is in the format {"Options: cat || mat":[1,2],"Options: once || many":[3,4]}.
        $annotationsarray = array();
        // Empty the fieldsarray.
        $fieldsarray = array();
        // It is not a square brackets placeholder question by default.
        $placeholdershavesquarebrackets = false;

        if ($questiondata->qtype == 'random') {
            // Random question context.
            if ($questiondata->questiontext) {
                $categorylist = question_categorylist($questiondata->category);
            } else {
                $categorylist = array($questiondata->category);
            }
            $randomquestion = ($this->choose_other_question($questiondata, array()));
            $questiontext = $randomquestion->questiontext;
            switch (get_class($randomquestion)) {
                case 'qtype_calculated_question':
                    $this->processcalculatedquestion($randomquestion, $solutions);
                    break;
                case 'qtype_calculatedmulti_single_question':
                    $this->processcalculatedmultiquestion($randomquestion, $solutions);
                    break;
                case 'qtype_calculatedmulti_multi_question':
                    $this->processcalculatedmultiquestion($randomquestion, $solutions);
                    break;
                case 'qtype_multianswer_question':
                    $this->processclozequestion($randomquestion, $solutions);
                    break;
                case 'qtype_calculatedsimple_question':
                    $this->processcalculatedquestion($randomquestion, $solutions);
                    break;
                case 'qtype_shortanswer_question':
                    $this->processshortanswerquestion($randomquestion, $solutions);
                    break;
                case 'qtype_multichoice_single_question':
                    $this->processmultichoicequestion($randomquestion, $solutions);
                    break;
                case 'qtype_multichoice_multi_question':
                    $this->processmultichoicequestion($randomquestion, $solutions);
                    break;
                case 'qtype_numerical_question':
                    $this->processmultichoicequestion($randomquestion, $solutions);
                    break;
                case 'qtype_truefalse_question':
                    $this->processmultichoicequestion($randomquestion, $solutions);
                    break;
                case 'qtype_match_question':
                    $this->processmultichoicequestion($randomquestion, $solutions);
                    break;
                case 'qtype_gapselect_question':
                    $this->processgapselectquestion($randomquestion, $solutions);
                    $placeholdershavesquarebrackets = true;
                    break;
                case 'qtype_kprime_question':
                    $this->processmultichoicequestion($randomquestion, $solutions);
                    break;
                case 'qtype_mtf_question':
                    $this->processmultichoicequestion($randomquestion, $solutions);
                    break;
                case 'qtype_ddwtos_question':
                    $this->processddwtosquestion($randomquestion, $solutions);
                    $placeholdershavesquarebrackets = true;
                    break;
                default:
                    break;
            }
        } else {
            // Not random question context.
            $questiontext = $this->getquestiontext($questiondata);
            // From here on the single question types.
            switch ($questiondata->qtype) {
                case 'multianswer':
                    $this->processclozequestion($questiondata, $solutions);
                    break;
                case 'calculated':
                    $this->processcalculatedquestion($questiondata, $solutions);
                    break;
                case 'calculatedmulti':
                    $this->processcalculatedmultiquestion($questiondata, $solutions);
                    break;
                case 'calculatedsimple':
                    $this->processcalculatedquestion($questiondata, $solutions);
                    break;
                case 'shortanswer':
                    $this->processshortanswerquestion($questiondata, $solutions);
                    break;
                case 'multichoice':
                    $this->processmultichoicequestion($questiondata, $solutions);
                    break;
                case 'numerical':
                    $this->processnumericalquestion($questiondata, $solutions);
                    break;
                case 'truefalse':
                    $this->processtruefalsequestion($questiondata, $solutions);
                    break;
                case 'match':
                    $this->processmatchingquestion($questiondata, $solutions);
                    break;
                case 'gapselect':
                    $this->processgapselectquestion($questiondata, $solutions);
                    $placeholdershavesquarebrackets = true;
                    break;
                case 'kprime':
                    $this->processkprimequestion($questiondata, $solutions);
                    break;
                case 'mtf':
                    $this->processmtfquestion($questiondata, $solutions);
                    break;
                case 'ddwtos':
                    $this->processddwtosquestion($questiondata, $solutions);
                    $placeholdershavesquarebrackets = true;
                    break;
                default:
                    break;
            }
        }
        // Replace the placeholders by the HTML to print.
        // The replacearray consists of tupels "#1": "the HTML to print", "#2": "the HTML to print" and so forth.
        if ($placeholdershavesquarebrackets == true) {
            $questiontext = preg_replace(array_map(array('quiz_solution_report', 'squareplaceholders'),
                array_keys($replacearray)),
                array_values($replacearray),
                $questiontext . $annotation);
        } else {
            $questiontext = preg_replace(array_map(array('quiz_solution_report', 'placeholders'),
                array_keys($replacearray)),
                array_values($replacearray),
                $questiontext . $annotation);
        }
        return $questiontext;
    }

    /**
     * Return the question text.
     *
     * @param object $questiondata the data defining a question.
     * @return mixed
     */
    public function getquestiontext($questiondata) {
        if (isset($questiondata->qtype) AND (in_array($questiondata->qtype, $this->nonprintableqtypes))) {
            return "<p>" . get_string('qtypenotdisplayable', 'quiz_solution',
                get_string('pluginname', 'qtype_' . $questiondata->qtype)) . "</p>\n";
        }
        return $questiondata->questiontext;
    }

    /**
     * Process the description "question type".
     *
     * @param object $questiondata the data defining a description question.
     */
    public function processdescription($questiondata) {
        // Description "question type".
        global $questiontext;

        $questiontext .= "1234";
    }

    /**
     * Process the cloze question type.
     *
     * The *1 asterisk with number comes in when there are more than one groups.
     * The * asterisk comes in when there is just one group.
     *
     * @param object $questiondata the data defining a cloze question.
     * @param bool $solutions whether to show the solutions.
     * @throws coding_exception
     */
    public function processclozequestion($questiondata, $solutions = false) {
        // Multiquestion (cloze) question type.
        global $annotation, $annotationnumbering, $replacearray, $annotationsarray, $fieldsarray;
        if (get_class($questiondata) == 'stdClass') {
            // When coming from 'normal' question context.
            $multiansweroptionscount = count($questiondata->options);
            // Check whether it has at least 1 question.
        }
        if (get_class($questiondata) == 'qtype_multianswer_question') {
            // When sent as randomly chosen question.
            $multiansweroptionscount = 1;
            // Do not check whether it has at least 1 question.
        }
        if ($multiansweroptionscount > 0) {
            if (get_class($questiondata) == 'stdClass') {
                // When coming from 'normal' question context.
                $multianswerquestionscount = count($questiondata->options->questions);
            }
            if (get_class($questiondata) == 'qtype_multianswer_question') {
                // When sent as randomly chosen question.
                $multianswerquestionscount = count($questiondata->subquestions);
            }
            if (get_class($questiondata) == 'stdClass') {
                // When coming from 'normal' question context.
                // Initialise the replacearray index.
                $i = 1;
                foreach ($questiondata->options->questions as $object) {
                    $size = 0;
                    $pulldownoptions = "";
                    $correctanswerscounter = 0;
                    $correctanswers = array();
                    if ($object->questiontext) {
                        $multianswerquestionsoptionscount = count($object->options);
                        if ($multianswerquestionsoptionscount > 0) {
                            $multianswerquestionsoptionsanswerscount = count($object->options->answers);
                            if ($multianswerquestionsoptionsanswerscount > 0) {
                                $pulldownoptions = "";
                                foreach ($object->options->answers as $answer) {
                                    if ($answer->fraction > 0.0000000) {
                                        $correctanswers[$correctanswerscounter]['answer'] = $answer->answer;
                                        $correctanswers[$correctanswerscounter]['percent'] = substr((string)100 *
                                                $answer->fraction, 0, 8) . "%";
                                        $correctanswerscounter++;
                                    }
                                    if ($object->qtype == 'numerical' && $answer->answerformat == 2) {
                                        // Numerical.
                                        // Input field should be at least size 3.
                                        if (strlen((string)$answer->answer) <= 3) {
                                            $size = 3;
                                        } else {
                                            $size = strlen((string)$answer->answer);
                                        }

                                        // However $size should not be over 50 (happens because of multilang).
                                        if ($size > 50) {
                                            $size = 50;
                                        }
                                        // Shorten correct answer.
                                        if ($solutions) {
                                            // Solution.
                                            if (count($correctanswers) == 1) {
                                                // Just one solution.
                                                // In the order type value size style.
                                                $replacearray["#$i"] = "<input type=\"text\"" .
                                                    " value=\"" . $correctanswers[0]['answer'] . "\"" .
                                                    " size=\"" . $size . "\"" .
                                                    " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                    "/>";
                                            } else {
                                                $spacesize = "";
                                                for ($j = 1; $j <= $size; $j++) {
                                                    $spacesize .= "&#160;&#160;";
                                                }
                                                $replacearray["$i"] = "<input type=\"text\"" .
                                                    " value=\"" . $spacesize . "\"" .
                                                    " size=\"" . $size . "\"" .
                                                    " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                    "/>&#160;<sup>*" . $annotationnumbering . "</sup>\n";
                                            }
                                        } else {
                                            // solution.
                                            // In the order type value size style.
                                            $spacesize = "";
                                            for ($j = 1; $j <= $size; $j++) {
                                                $spacesize .= "&#160;&#160;";
                                            }
                                            $replacearray["$i"] = "<input type=\"text\"" .
                                                " value=\"" . $spacesize . "\"" .
                                                " size=\"" . $size . "\"" .
                                                " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                "/>";
                                        }
                                    }
                                    // Ende numerical.
                                    // Shortanswer (2) gäbe eine Linie.
                                    if (($object->qtype == 'shortanswer' && $answer->answerformat == 0) OR
                                        ( $object->qtype == 'shortanswer' && $answer->answerformat == 2)) {
                                        // Input field should be at least size 3.
                                        if (strlen((string)$answer->answer) > $size) {
                                            $size = strlen((string)$answer->answer);
                                        }
                                        if ($size <= 3) {
                                            $size = 3;
                                        }
                                        // However $size should not be over 50 (happens because of multilang).
                                        if ($size > 50) {
                                            $size = 50;
                                        }
                                        if ($solutions) {
                                            // Solution.
                                            // In the order type value size style.
                                            if (count($correctanswers) == 1) {
                                                // Just one solution.
                                                $replacearray["#$i"] = "<input type=\"text\"" .
                                                    " value=\"" . $correctanswers[0]['answer'] . "\"" .
                                                    " size=\"" . $size . "\"" .
                                                    " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                    "/>";
                                            } else {
                                                $spacesize = "";
                                                for ($j = 1; $j <= $size; $j++) {
                                                    $spacesize .= "&#160;&#160;";
                                                }
                                                $replacearray["$i"] = "<input type=\"text\"" .
                                                    " value=\"" . $spacesize . "\"" .
                                                    " size=\"" . $size . "\"" .
                                                    " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                    "/>&#160;<sup>*" . $annotationnumbering . "</sup>\n";
                                            }
                                        } else {
                                            // solution.
                                            // In the order type value size style.
                                            $spacesize = "";
                                            for ($j = 1; $j <= $size; $j++) {
                                                $spacesize .= "&#160;&#160;";
                                            }
                                            $fieldsarray["$i"] = "<input type=\"text\"" .
                                                " value=\"" . $spacesize . "\"" .
                                                " size=\"" . $size . "\"" .
                                                " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                "/>";
                                            $replacearray["#$i"] = "<input type=\"text\"" .
                                                " value=\"" . $spacesize . "\"" .
                                                " size=\"" . $size . "\"" .
                                                " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                "/>";
                                        }
                                    }

                                    if (($object->qtype == 'multichoice' && $answer->answerformat == 0) OR
                                        ( $object->qtype == 'multichoice' && $answer->answerformat == 1)) {
                                        // Multichoice.
                                        if (strlen((string)$answer->answer) > $size) {
                                            $size = strlen((string)$answer->answer);
                                        }
                                        if ($size <= 3) {
                                            $size = 3;
                                        }
                                        if ($pulldownoptions === '') {
                                            // The first entry.
                                            $pulldownoptions .= $answer->answer;
                                        } else {
                                            $pulldownoptions .= " || " . $answer->answer;
                                        }

                                        if ($solutions) {
                                            // Solution.
                                            // In the order type value size style.
                                            if (count($correctanswers) == 1) {
                                                // Just one solution.
                                                // In the order type value size style.
                                                $size = strlen((string)$correctanswers[0]['answer']);
                                                // However $size should not be over 50 (happens because of multilang).
                                                if ($size > 50) {
                                                    $size = 50;
                                                }
                                                $replacearray["#$i"] = "<input type=\"text\"" .
                                                    " value=\"" . $correctanswers[0]['answer'] . "\"" .
                                                    " size=\"" . $size . "\"" .
                                                    " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                    "/>";
                                            } else {
                                                $spacesize = "";
                                                for ($j = 1; $j <= $size; $j++) {
                                                    $spacesize .= "&#160;&#160;";
                                                }
                                                $replacearray["$i"] = "<input type=\"text\"" .
                                                    " value=\"" . $spacesize . "\"" .
                                                    " size=\"" . $size . "\"" .
                                                    " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                    "/>&#160;<sup>*" . $annotationnumbering . "</sup>\n";
                                            }
                                        } else {
                                            // solution.
                                            // In the order type value size style.
                                            $spacesize = "";
                                            for ($j = 1; $j <= $size; $j++) {
                                                $spacesize .= "&#160;&#160;";
                                            }
                                            $fieldsarray["$i"] = "<input type=\"text\"" .
                                                " value=\"" . $spacesize . "\"" .
                                                " size=\"" . $size . "\"" .
                                                " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                "/>";
                                            $replacearray["#$i"] = "<input type=\"text\"" .
                                                " value=\"" . $spacesize . "\"" .
                                                " size=\"" . $size . "\"" .
                                                " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                                "/>&#160;<sup>*" . $annotationnumbering . "</sup>\n";
                                        }
                                    }
                                    // End multichoice.
                                    if ($object->qtype == 'calculated' && $answer->answerformat == 0) {
                                        // Calculated .
                                        if ($pulldownoptions === '') {
                                            // The first entry.
                                            $pulldownoptions .= $answer->answer;
                                        } else {
                                            $pulldownoptions .= " || " . $answer->answer;
                                        }
                                        $replacearray["#$i"] = "<input type=\"text\" size=\10\"" .
                                            " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                            "/>&#160;<sup>*" . $annotationnumbering . "</sup>\n";
                                    }
                                    // End calculated.
                                }
                                if (($object->qtype == 'multichoice' && $answer->answerformat == 0) OR
                                    ( $object->qtype == 'multichoice' && $answer->answerformat == 1)) {
                                    if (array_key_exists($pulldownoptions,
                                        $annotationsarray)) {
                                        $annotationsarray[$pulldownoptions][] = $i;
                                    } else {
                                        // Only if it doesn't already exist.
                                        $annotationsarray[$pulldownoptions] = array($i);
                                    }
                                    $annotationnumbering++;
                                }
                                if (($object->qtype == 'shortanswer' && (count($correctanswers) > 1)) OR
                                    ( $object->qtype == 'numerical' && (count($correctanswers) > 1))) {
                                    // More than one solution.
                                    if ($solutions = true) {
                                        // Solution.
                                        if ($annotationnumbering == 1) {
                                            $annotation .= "<p>&#160;</p>\n";
                                        }
                                        $annotation .= "<sup>*" . $annotationnumbering . "</sup>&#160;" .
                                            get_string('multiplesolutions', 'quiz_solution') . ": ";
                                        $firstsolutionanswer = 0;
                                        foreach ($correctanswers as $solutionanswer) {
                                            if ($firstsolutionanswer > 0) {
                                                $annotation .= " / ";
                                            }
                                            $annotation .= $solutionanswer['answer'] . " (" . $solutionanswer['percent'] . ")";
                                            $firstsolutionanswer++;
                                        }
                                        $annotation .= "<br />\n";
                                        $annotationnumbering++;
                                    } else {
                                        // solution.
                                        foreach ($object->options->answers as $answer) {
                                            if (strlen((string)$answer->answer) > $size) {
                                                $size = strlen((string)$answer->answer);
                                            }
                                        }
                                        if ($size <= 3) {
                                            $size = 3;
                                        }
                                        // However $size should not be over 50 (happens because of multilang).
                                        if ($size > 50) {
                                            $size = 50;
                                        }
                                        // solution.
                                        // In the order type value size style.
                                        $spacesize = "";
                                        for ($j = 1; $j <= $size; $j++) {
                                            $spacesize .= "&#160;&#160;";
                                        }
                                        $fieldsarray["$i"] = "<input type=\"text\"" .
                                            " value=\"" . $spacesize . "\"" .
                                            " size=\"" . $size . "\"" .
                                            " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                            "/>";
                                        $replacearray["#$i"] = "<input type=\"text\"" .
                                            " value=\"" . $spacesize . "\"" .
                                            " size=\"" . $size . "\"" .
                                            " style=\"border: 1px dashed #000000; height: 24px;\"" .
                                            "/>";
                                    }
                                }
                            }
                        }
                    }
                    $i++;
                }
            }
            if (get_class($questiondata) == 'qtype_multianswer_question') {
                // When sent as randomly chosen question.
                $i = 1;
                foreach ($questiondata->subquestions as $object) {
                    if ($object->questiontext) {
                        $size = 0;
                        $correctanswerscounter = 0;
                        $correctanswers = array();
                        $pulldownoptionnumbering = 0;
                        $pulldownoptions = "";
                        $multianswerquestionsoptionsanswerscount = count($object->answers);
                        if ($multianswerquestionsoptionsanswerscount > 0) {
                            $pulldownoptionnumbering = 0;
                            $pulldownoptions = "";
                            if (get_class($object->qtype) == 'qtype_numerical') {
                                // Numerical.
                                $correctanswers[$correctanswerscounter]['answer'] = $object->get_correct_answer()->answer;
                                $correctanswers[$correctanswerscounter]['percent'] = "100%";
                                // Input field should be at least size 3.
                                if (strlen((string)$object->get_correct_answer()->answer) <= 3) {
                                    $size = 3;
                                } else {
                                    $size = strlen((string)$object->get_correct_answer()->answer);
                                }
                                // However $size should not be over 50 (happens because of multilang).
                                if ($size > 50) {
                                    $size = 50;
                                }
                                if (count($correctanswers) == 1) { // Just one solution.
                                    $replacearray["#$i"] = "<input type=\"text\" size=\"" . $size .
                                        "\" style=\"border: 1px dashed #000000; height: 24px;\" value=\"\"/>";
                                } else {
                                    $fieldsarray["#$i"] = "<input type=\"text\" size=\"" . $size .
                                        "\" style=\"border: 1px dashed #000000; height: 24px;\" value=\"\"/>";
                                    $replacearray["#$i"] = "<input type=\"text\" size=\"" . $size .
                                        "\" style=\"border: 1px dashed #000000; height: 24px;\" value=\"\"/>&#160;<sup>*" .
                                        $annotationnumbering . "</sup>\n";
                                    if ($annotation === "") {
                                        $annotation = "<p>&#160;</p>\n";
                                    }
                                    $annotation .= "<sup>*" . $i . "</sup>&#160;" . get_string('multiplesolutions',
                                            'quiz_solution') . ": ";
                                    $firstsolutionanswer = 0;
                                    foreach ($correctanswers as $solutionanswer) {
                                        if ($firstsolutionanswer > 0) {
                                            $annotation .= " / ";
                                        }
                                        $annotation .= $solutionanswer['answer'] . " (" . $solutionanswer['percent'] . ")";
                                        $firstsolutionanswer++;
                                    }
                                    $annotation .= "<br />\n";
                                    $annotationnumbering++;
                                }
                            } // Ende numerical.
                            /* shortanswer (2) gäbe eine Linie */
                            if (get_class($object->qtype) == 'qtype_shortanswer') {
                                if ($solutions) { // Solution.
                                    foreach ($object->answers as $answer) {
                                        if ($answer->fraction > 0.0000000) {
                                            $correctanswers[$correctanswerscounter]['answer'] = $answer->answer;
                                            $correctanswers[$correctanswerscounter]['percent'] =
                                                substr((string)100 * $answer->fraction, 0, 8) . "%";
                                            /* multiple answers could be correct, set the correct answer length to the longest */
                                            if (strlen((string)$answer->answer) > $size) {
                                                $size = strlen((string)$answer->answer);
                                            }
                                            $correctanswerscounter++;
                                        }
                                    }
                                } else { // solution.
                                    foreach ($object->answers as $answer) {
                                        if (strlen((string)$answer->answer) > $size) {
                                            $size = strlen((string)$answer->answer);
                                        }
                                    }
                                }
                                // Input field should be at least size 3.
                                if ($size <= 3) {
                                    $size = 3;
                                }
                                // However $size should not be over 50 (happens because of multilang).
                                if ($size > 50) {
                                    $size = 50;
                                }
                                if ($solutions) { // Solution.
                                    if (count($correctanswers) == 1) { // Just one solution.
                                        $replacearray["#$i"] = "<input type=\"text\" size=\"" . $size .
                                            "\" style=\"border: 1px dashed #000000; height: 24px;\" value=\"" .
                                            $correctanswers[0]['answer'] . "\"/>\n";
                                    } else {
                                        $replacearray["#$i"] = "<input type=\"text\" size=\"" . $size .
                                            "\" style=\"border: 1px dashed #000000; height: 24px;\"/>&#160;<sup>*" .
                                            $annotationnumbering . "</sup>\n";
                                        if ($annotation === "") {
                                            $annotation = "<p>&#160;</p>\n";
                                        }
                                        $annotation .= "<sup>*" . $annotationnumbering . "</sup>&#160;" .
                                            get_string('multiplesolutions', 'quiz_solution') . ": ";
                                        $firstsolutionanswer = 0;
                                        foreach ($correctanswers as $solutionanswer) {
                                            if ($firstsolutionanswer > 0) {
                                                $annotation .= " / ";
                                            }
                                            $annotation .= $solutionanswer['answer'] . " (" . $solutionanswer['percent'] . ")";
                                            $firstsolutionanswer++;
                                        }
                                        $annotation .= "<br />\n";
                                        $annotationnumbering++;
                                    }
                                } else { // solution.
                                    $replacearray["#$i"] = "<input type=\"text\" size=\"" . $size .
                                        "\" style=\"border: 1px dashed #000000; height: 24px;\" value=\"\">";
                                }
                            }

                            if (get_class($object->qtype) == 'qtype_multichoice') {
                                // Multichoice.
                                // Rewrite pulldown menu answer options.
                                foreach ($object->answers as $answer) {
                                    if ($solutions) { // Solution.
                                        if ($answer->fraction > 0.0000000) {
                                            $correctanswers[$correctanswerscounter]['answer'] = $answer->answer;
                                            $correctanswers[$correctanswerscounter]['percent'] =
                                                substr((string)100 * $answer->fraction, 0, 8) . "%";
                                            // Multiple answers could be correct, set the correct answer length to the longest.
                                            if (strlen((string)$answer->answer) > $size) {
                                                $size = strlen((string)$answer->answer);
                                            }
                                            $correctanswerscounter++;
                                        }
                                    } else { // solution.
                                        // Multiple answers could be correct, set the correct answer length to the longest.
                                        if (strlen((string)$answer->answer) > $size) {
                                            $size = strlen((string)$answer->answer);
                                        }
                                    }
                                    if ($pulldownoptions === '') {
                                        // The first entry.
                                        $pulldownoptions .= $answer->answer;
                                    } else {
                                        $pulldownoptions .= " || " . $answer->answer;
                                    }
                                    $pulldownoptionnumbering++;
                                }
                                // However $size should not be over 50 (happens because of multilang).
                                if ($size > 50) {
                                    $size = 50;
                                }
                                if ($solutions) { // Solution.
                                    if (count($correctanswers) == 1) { // Just one solution.
                                        $replacearray["#$i"] = "<input type=\"text\" size=\"" . $size .
                                            "\" style=\"border: 1px dashed #000000; height: 24px;\" value=\"" .
                                            $correctanswers[0]['answer'] . "\"/>&#160;<sup>*" . $i . "</sup>\n";
                                    } else {
                                        $replacearray["#$i"] = "<input type=\"text\" size=\"" . $size .
                                            "\" style=\"border: 1px dashed #000000; height: 24px;\"/>&#160;<sup>*" .
                                            $annotationnumbering . "</sup>\n";
                                    }
                                } else { // solution.
                                    $fieldsarray[$i] = "<input type=\"text\" size=\"" . $size .
                                        "\" style=\"border: 1px dashed #000000; height: 24px;\" value=\"\"/>";
                                    $replacearray["#$i"] = "<input type=\"text\" size=\"" . $size .
                                        "\" style=\"border: 1px dashed #000000; height: 24px;\"/>&#160;<sup>*" .
                                        $annotationnumbering . "</sup>\n";
                                }
                                if (array_key_exists($pulldownoptions,
                                    $annotationsarray)) {
                                    $annotationsarray["$pulldownoptions"][] = $i;
                                } else {
                                    // Only if it doesn't already exist.
                                    $annotationsarray["$pulldownoptions"] = array($i);
                                }
                                $annotationnumbering++;
                            } // Ende multichoice.
                        }
                    }
                    $i++;
                }
            }
        }
        // Routine to sort out multiple identical option fields.
        $singleannotationcounter = 1;
        $annotation = "";

        foreach ($annotationsarray as $uniqueannotation => $annotationvalues) {
            foreach ($annotationvalues as $annotationfield) {
                $replacearray["#$annotationfield"] = $fieldsarray[$annotationfield] . "&#160;<sup>*" . $singleannotationcounter .
                    "</sup>\n";
            }
            // Shuffle $uniqueannotation.
            $uniqueannotationelements = explode(' || ', $uniqueannotation);
            shuffle($uniqueannotationelements);
            $annotation .= "<sup>*" . $singleannotationcounter . "</sup>&#160;" . get_string('options', 'quiz_solution') .
                ": " . implode(' || ', $uniqueannotationelements) . "<br />\n";
            $singleannotationcounter++;
        }
    }

    /**
     * Process the calculated question type and the calculated simple question type.
     *
     * @param object $questiondata the data defining a calculated or calculated simple question.
     * @param bool $solutions whether to show the solutions.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     */
    public function processcalculatedquestion($questiondata, $solutions = false) {
        // Calculated question type, also used for calculated simple question type.
        global $DB, $questiontext, $replacearray;
        $datasetdefs = array();
        $size = 0; /* correct answer length */
        $correctanswers = array();

        /* Get the dataset definitions. */
        if (!empty($questiondata->id)) {
            $sql = "SELECT i.*
                      FROM {question_datasets} d, {question_dataset_definitions} i
                     WHERE d.question = ? AND d.datasetdefinition = i.id";
            if ($records = $DB->get_records_sql($sql, array($questiondata->id))) {
                foreach ($records as $r) {
                    $def = $r;
                    if ($def->category == '0') {
                        $def->status = 'private';
                    } else {
                        $def->status = 'shared';
                    }
                    $def->type = 'calculated';
                    list($distribution, $min, $max, $dec) = explode(':', $def->options, 4);
                    $def->distribution = $distribution;
                    $def->minimum = $min;
                    $def->maximum = $max;
                    $def->decimals = $dec;
                    if ($def->itemcount > 0) {
                        // Get the datasetitems.
                        $def->items = array();
                        if ($items = $this->get_database_dataset_items($def->id)) {
                            $n = 0;
                            foreach ($items as $ii) {
                                $n++;
                                $def->items[$n] = new stdClass();
                                $def->items[$n]->itemnumber = $ii->itemnumber;
                                $def->items[$n]->value = $ii->value;
                            }
                            $def->number_of_items = $n;
                        }
                    }
                    $datasetdefs["{$r->name}"] = $def;
                }
            }
        }
        $datasetdefscount = 0;
        $datasetdefscount = count($datasetdefs);

        foreach ($datasetdefs as $dataset) {
            $datasetvarname = $dataset->name;
            // Adjust to the correct number of decimals.
            $replacearray[$datasetvarname] = $dataset->items[rand(1, count($dataset->items))]->value;
        }
        $calculatedquestionoptionsanswerscount = 0;

        if (get_class($questiondata) == 'stdClass') {
            /* the answers are located differently depending on which class it is, stdClass or qtype_calculated_question */
            $calculatedquestionoptionsanswerscount = count($questiondata->options->answers);
        }
        if (get_class($questiondata) == 'qtype_calculated_question') {
            /* the answers are located differently depending on which class it is, stdClass or qtype_calculated_question */
            $calculatedquestionoptionsanswerscount = count($questiondata->answers);
        }
        if (get_class($questiondata) == 'qtype_calculatedsimple_question') {
            /* the answers are located differently depending on which class it is, stdClass or qtype_calculated_question */
            $calculatedquestionoptionsanswerscount = count($questiondata->answers);
        }
        if ($calculatedquestionoptionsanswerscount > 0) {
            if (get_class($questiondata) == 'stdClass') {
                foreach ($questiondata->options->answers as $answer) {
                    // Answers are of class stdClass.
                    $vs = new qtype_calculated_variable_substituter(
                        $replacearray, get_string('decsep', 'langconfig'));
                    $formula = $answer->answer;
                    $correctanswer = $vs->calculate($formula);
                    $correctanswers[] = $correctanswer;
                    // Multiple answers could be correct, set the correct answer length to the longest.
                    if (strlen((string)$correctanswer) > $size) {
                        $size = strlen((string)$correctanswer);
                    }
                }
            }
            if (get_class($questiondata) == 'qtype_calculated_question') {
                foreach ($questiondata->answers as $answer) {
                    // Answers are of class qtype_numerical_answer.
                    $vs = new qtype_calculated_variable_substituter(
                        $replacearray, get_string('decsep', 'langconfig'));
                    $formula = $answer->answer;
                    $correctanswer = $vs->calculate($formula);
                    $correctanswers[] = $correctanswer;
                    // Multiple answers could be correct, set the correct answer length to the longest.
                    if (strlen((string)$correctanswer) > $size) {
                        $size = strlen((string)$correctanswer);
                    }
                }
            }
            // Input field should be at least size 3.
            if ($size <= 3) {
                $size = 3;
            }
            // In the order type value size style.
            $spacesize = "";
            for ($j = 1; $j <= $size; $j++) {
                $spacesize .= "&#160;&#160;";
            }
            $questiontext .= "<input type=\"text\"" .
                " value=\"" . $spacesize . "\"" .
                " size=\"" . $size . "\"" .
                " style=\"border: 1px dashed #000000; height: 24px;\"" .
                "/>\n";
        }
    }

    /**
     * Process the multichoice question type.
     *
     * @param object $questiondata the data defining a multichoice question.
     * @param bool $solutions whether to show the solutions.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function processcalculatedmultiquestion($questiondata, $solutions = false) {
        // Calculated multichoice question type.
        global $CFG, $DB, $questiontext, $replacearray, $annotation;
        $datasetdefs = array();
        $size = 0; /* correct answer length */
        $correctanswers = array();
        $annotationnumbering = 0;
        $pulldownoptionnumbering = 0;
        $pulldownoptions = array();
        $pulldownoptionstring = "";
        $i = 1;
        $datasetdefs = array();
        if (!empty($questiondata->id)) {
            $sql = "SELECT i.*
                      FROM {question_datasets} d, {question_dataset_definitions} i
                     WHERE d.question = ? AND d.datasetdefinition = i.id";
            if ($records = $DB->get_records_sql($sql, array($questiondata->id))) {
                foreach ($records as $r) {
                    $def = $r;
                    if ($def->category == '0') {
                        $def->status = 'private';
                    } else {
                        $def->status = 'shared';
                    }
                    $def->type = 'calculatedmulti';
                    list($distribution, $min, $max, $dec) = explode(':', $def->options, 4);
                    $def->distribution = $distribution;
                    $def->minimum = $min;
                    $def->maximum = $max;
                    $def->decimals = $dec;
                    if ($def->itemcount > 0) {
                        // Get the datasetitems.
                        $def->items = array();
                        if ($items = $this->get_database_dataset_items($def->id)) {
                            $n = 0;
                            foreach ($items as $ii) {
                                $n++;
                                $def->items[$n] = new stdClass();
                                $def->items[$n]->itemnumber = $ii->itemnumber;
                                $def->items[$n]->value = $ii->value;
                            }
                            $def->number_of_items = $n;
                        }
                    }
                    $datasetdefs["{$r->name}"] = $def;
                }
            }
        }
        $datasetdefscount = 0;
        $datasetdefscount = count($datasetdefs);

        $vsarray = array();
        foreach ($datasetdefs as $dataset) {
            $datasetvarname = $dataset->name;
            // Adjust to the correct number of decimals.
            $randomvalue = $dataset->items[rand(1, count($dataset->items))]->value;
            $replacearray[$datasetvarname] = $randomvalue;
        }
        // Rewrite pulldown menu answer options, calculate correct data length.
        if (get_class($questiondata) == 'stdClass') {
            foreach ($questiondata->options->answers as $answer) {
                // Answers are of class stdClass.
                $vs = new qtype_calculated_variable_substituter(
                    $replacearray, get_string('decsep', 'langconfig'));
                // Throw away {= and }.
                $strippedformula = substr($answer->answer, 2, strlen($answer->answer) - 3);
                $option = $vs->calculate($strippedformula);
                if ($answer->fraction > 0.0000000) {
                    $correctanswers[] = $option;
                    // Multiple answers could be correct, set the correct answer length to the longest.
                    if (strlen((string)$option) > $size) {
                        $size = strlen((string)$option);
                    }
                }
                $pulldownoptions[] = $option;
            }
        }
        if ((get_class($questiondata) == 'qtype_calculatedmulti_single_question') OR
            ( get_class($questiondata) == 'qtype_calculatedmulti_multi_question')) {
            foreach ($questiondata->answers as $answer) {
                // Answers are of class qtype_calculatedmulti_single_question.
                $vs = new qtype_calculated_variable_substituter(
                    $replacearray, get_string('decsep', 'langconfig'));
                // Throw away {= and }.
                $strippedformula = substr($answer->answer, 2, strlen($answer->answer) - 3);
                $option = $vs->calculate($strippedformula);
                if ($answer->fraction > 0.0000000) {
                    $correctanswers[] = $option;
                    // Multiple answers could be correct, set the correct answer length to the longest.
                    if (strlen((string)$option) > $size) {
                        $size = strlen((string)$option);
                    }
                }
                $pulldownoptions[] = $option;
            }
        }
        if ($questiondata->options->shuffleanswers == 1) {
            // Shuffle the array.
            shuffle($pulldownoptions);
        }
        foreach ($pulldownoptions as $pulldownoption) {
            if ($pulldownoptionnumbering == 0) {
                $pulldownoptionstring .= $pulldownoption;
            } else {
                $pulldownoptionstring .= " || " . $pulldownoption;
            }
            $pulldownoptionnumbering++;
        }

        // Input field should be at least size 3.
        if ($size <= 3) {
            $size = 3;
        }
        if (get_class($questiondata) == 'stdClass') {
            if ($questiondata->options->single != 1) {
                $questiontext .= get_string('pleasecheckoneormoreanswers', 'lesson') . "<br/>\n";
            }
        }
        if (get_class($questiondata) == 'qtype_calculatedmulti_multi_question') {
            $questiontext .= get_string('pleasecheckoneormoreanswers', 'lesson') . "<br/>\n";
        }
        $questiontext .= "<br />\n<input type=\"text\" size=\"" . $size .
            "\" style=\"border: 1px dashed #000000; height: 24px;\"/>&#160;<sup>*</sup>\n";
        if ($annotation === "") {
            $annotation = "<p>&#160;</p>\n";
        }
        $annotation .= "<sup>*</sup>&#160;" . get_string('options', 'quiz_solution') . ": $pulldownoptionstring\n";
    }

    /**
     * Process the shortanswer question type.
     *
     * @param object $questiondata the data defining a random question.
     * @param bool $solutions whether to show the solutions.
     */
    public function processshortanswerquestion($questiondata, $solutions = false) {
        // Shortanswer question type.
        global $DB, $questiontext;
        // In the order type value size style.
        $spacesize = "";
        for ($j = 1; $j <= 80; $j++) {
            $spacesize .= "&#160;";
        }
        $questiontext .= "<input type=\"text\"" .
            " value=\"" . $spacesize . "\"" .
            " size=\"80\"" .
            " style=\"border: 1px dashed #000000; height: 24px;\"" .
            "/>\n";
    }

    /**
     * Process the multichoice_single and multichoice_multi question type.
     *
     * @param object $questiondata the data defining a multichoice_single or multichoice_multi question.
     * @param bool $solutions whether to show the solutions.
     */
    public function processmultichoicequestion($questiondata, $solutions = false) {
        // Multichoice_single and Multichoice_multi question type.
        global $CFG, $DB, $questiontext, $replacearray;
        $multichoiceoptionnumbering = 0;
        $multichoiceoptions = array();
        $multichoiceoptionstring = "";
        if (get_class($questiondata) == 'stdClass') {
            foreach ($questiondata->options->answers as $answer) {
                // Remove outer <p> </p>.
                $multichoiceoptions[] = preg_replace('!^<p>(.*?)</p>$!i', '$1', $answer->answer); /* remove outer <p> </p> */
            }
        }
        if ((get_class($questiondata) == 'qtype_multichoice_single_question') OR
            ( get_class($questiondata) == 'qtype_multichoice_multi_question')) {
            foreach ($questiondata->answers as $answer) {
                // Remove outer <p> </p>.
                $multichoiceoptions[] = preg_replace('/<p[^>]*>(.*)<\/p[^>]*>/', '$1',
                    $answer->answer);
            }
        }
        if ($questiondata->options->shuffleanswers == 1) {
            // Shuffle the array.
            shuffle($multichoiceoptions);
        }
        foreach ($multichoiceoptions as $multichoiceoption) {
            if ($multichoiceoptionnumbering == 0) {
                $multichoiceoptionstring .= "<p><input type=\"checkbox\" />&#160;" . $multichoiceoption . "</p>\n";
            } else {
                $multichoiceoptionstring .= "<p><input type=\"checkbox\" />&#160;" . $multichoiceoption . "</p>\n";
            }
            $multichoiceoptionnumbering++;
        }
        $questiontext .= $multichoiceoptionstring;
    }

    /**
     * Process the numerical question type.
     *
     * @param object $questiondata the data defining a numerical question.
     * @param bool $solutions whether to show the solutions.
     */
    public function processnumericalquestion($questiondata, $solutions = false) {
        // Numerical question type.
        global $CFG, $DB, $questiontext, $replacearray;
        if (get_class($questiondata) == 'stdClass') {
            $numericalquestionoptionsanswerscount = count($questiondata->options->answers);
        }
        if (get_class($questiondata) == 'qtype_numerical_question') {
            $numericalquestionoptionsanswerscount = count($questiondata->answers);
        }
        if ($numericalquestionoptionsanswerscount > 0) {
            $size = 3;
            if (get_class($questiondata) == 'stdClass') {
                foreach ($questiondata->options->answers as $answer) {
                    // Input field should be at least size 3, but only one (and the longest) in case of multiple correct
                    // responses.
                    if ((strlen((string)$answer->answer)) > $size) {
                        $size = strlen((string)$answer->answer);
                    }
                }
            }
            if (get_class($questiondata) == 'qtype_numerical_question') {
                foreach ($questiondata->answers as $answer) {
                    // Input field should be at least size 3, but only one (and the longest) in case of multiple correct
                    // responses.
                    if ((strlen((string)$answer->answer)) > $size) {
                        $size = strlen((string)$answer->answer);
                    }
                }
            }
            $questiontext = "<p>" . $questiontext;
            // In the order type value size style.
            $spacesize = "";
            for ($j = 1; $j <= $size; $j++) {
                $spacesize .= "&#160;&#160;";
            }
            $questiontext .= "</p>\n<p>" .
                "<input type=\"text\"" .
                " value=\"" . $spacesize . "\"" .
                " size=\"" . $size . "\"" .
                " style=\"border: 1px dashed #000000; height: 24px;\"" .
                "/></p>\n";
        }
    }

    /**
     * Process the truefalse question type.
     *
     * @param object $questiondata the data defining a truefalse question.
     * @param bool $solutions whether to show the solutions.
     * @throws coding_exception
     */
    public function processtruefalsequestion($questiondata, $solutions = false) {
        // Truefalse question type.
        global $CFG, $DB, $questiontext, $replacearray;
        require_once($CFG->dirroot . '/question/type/truefalse/questiontype.php');
        $truefalseoptionnumbering = 0;
        $truefalseoptionstring = "";
        $truefalseoptions = array();

        if (get_class($questiondata) == 'stdClass') {
            foreach ($questiondata->options->answers as $answer) {
                // Remove outer <p> </p>.
                $truefalseoptions[] = preg_replace('!^<p>(.*?)</p>$!i', '$1', $answer->answer);
            }
        }
        if (get_class($questiondata) == 'qtype_truefalse_question') {
            $truefalseoptions = array(
                0 => get_string('false', 'qtype_truefalse'),
                1 => get_string('true', 'qtype_truefalse'));
        }

        foreach ($truefalseoptions as $truefalseoption) {
            if ($truefalseoptionnumbering == 0) {
                $truefalseoptionstring .= "<p><input type=\"radio\" />&#160;" . $truefalseoption . "</p>\n";
            } else {
                $truefalseoptionstring .= "<p><input type=\"radio\" />&#160;" . $truefalseoption . "</p>\n";
            }
            $truefalseoptionnumbering++;
        }
        $questiontext .= $truefalseoptionstring;
    }

    /**
     * Process the matching question type.
     *
     * @param object $questiondata the data defining a matching question.
     * @param bool $solutions whether to show the solutions.
     * @throws coding_exception
     */
    public function processmatchingquestion($questiondata, $solutions = false) {
        // Matching question type.
        global $questiontext;
        $matchoptionnumbering = 0;
        $matchoptionstring = "";
        $matchoptions = array();
        // Input field should be at least size 3.
        $size = 3;
        if (get_class($questiondata) == 'stdClass') {
            foreach ($questiondata->options->subquestions as $answer) {
                // Looking for the largest option.
                if (trim($answer->questiontext) != '') {
                    $answeroption = $answer->answertext;
                    if (strlen((string)$answeroption) > $size) {
                        $size = strlen((string)$answeroption);
                    }
                }
            }
            foreach ($questiondata->options->subquestions as $answer) {
                $matchoptions[] = $answer->answertext;
                if (trim($answer->questiontext) != '') {
                    $questiontext .= "<p>";
                    // Remove outer <p> </p>.
                    $questiontext .= preg_replace('!^<p>(.*?)</p>$!i', '$1', $answer->questiontext);
                    $questiontext .= "\n";
                    // In the order type value size style.
                    $spacesize = "";
                    for ($j = 1; $j <= $size; $j++) {
                        $spacesize .= "&#160;";
                    }
                    $questiontext .= "&#160;<input type=\"text\"" .
                        " value=\"" . $spacesize . "\"" .
                        " size=\"" . $size . "\"" .
                        " style=\"border: 1px dashed #000000; height: 24px;\"" .
                        "/>&#160;<sup>*</sup></p>\n";
                }
            }
            if ($questiondata->options->shuffleanswers == 1) {
                // Shuffle the array.
                shuffle($matchoptions);
            }
        }
        if (get_class($questiondata) == 'qtype_match_question') {
            foreach ($questiondata->choices as $choice) {
                // Looking for the largest option.
                if (trim($choice) != '') {
                    if (strlen((string)$choice) > $size) {
                        $size = strlen((string)$choice);
                    }
                }
            }
            foreach ($questiondata->stems as $question) {
                if (trim($question) != '') {
                    $questiontext .= "<p>";
                    // Remove outer <p> </p>.
                    $questiontext = preg_replace('/<p[^>]*>(.*)<\/p[^>]*>/', '$1', $question);

                    $questiontext .= "\n";
                    $questiontext .= "&#160;<input type=\"text\" size=\"" . $size .
                        "\" style=\"border: 1px dashed #000000; height: 24px;\"/>&#160;<sup>*</sup></p>\n";
                }
            }
            foreach ($questiondata->choices as $choice) {
                $matchoptions[] = $choice;
            }
            if ($questiondata->shufflestems = 1) {
                // Shuffle the array.
                shuffle($matchoptions);
            }
        }
        foreach ($matchoptions as $matchoption) {
            if ($matchoptionnumbering == 0) {
                $matchoptionstring .= $matchoption;
            } else {
                $matchoptionstring .= " || " . $matchoption;
            }
            $matchoptionnumbering++;
        }
        $questiontext = "<p>" . $questiontext . "</p>\n";
        $questiontext .= "<p><sup>*</sup>&#160;" . get_string('options', 'quiz_solution') . ": " .
            $matchoptionstring . "</p>\n";
    }

    /**
     * Process the missing words question type.
     * The *1 asterisk with number comes in when there are more than one groups.
     * The * asterisk comes in when there is just one group.
     *
     * @param object $questiondata the data defining a missing words question.
     * @param bool $solutions whether to show the solutions.
     * @throws coding_exception
     */
    public function processgapselectquestion($questiondata, $solutions = false) {
        // Missing words question type.

        // Cloze has questions iterated 1, 2, 3, 4 and they have each the options individually. Repeating.
        // Missing words has answers and feedback does deal with feedback.
        global $questiontext, $annotationnumbering, $replacearray, $annotation, $annotationsarray, $fieldsarray;
        // Input field should be at least size 3.
        $size = 3;
        $gapselectoptions = "";
        if (get_class($questiondata) == 'stdClass') {
            $i = 1;
            foreach ($questiondata->options->answers as $answer) {
                // Looking for the largest option.
                if (trim($answer->answer) != '') {
                    $answeroption = $answer->answer;
                    if (strlen((string)$answeroption) > $size) {
                        $size = strlen((string)$answeroption);
                    }
                }
            }
            $gapselectoptionoptions = array();
            $gapoptionsarray = array();
            foreach ($questiondata->options->answers as $answer) {
                // Build an array attributing the gaps to the options.
                $gapoptionsarray[] = array($answer->feedback => $i);
                // Build an array with the options.
                if (!isset($gapselectoptionoptions[$answer->feedback])) {
                    // The first entry.
                    $gapselectoptionoptions[$answer->feedback] = $answer->answer;
                } else {
                    $gapselectoptionoptions[$answer->feedback] = $gapselectoptionoptions[$answer->feedback] . " || " .
                        $answer->answer;
                }

                if ($gapselectoptions === '') {
                    // The first entry.
                    $gapselectoptions .= $answer->answer;
                } else {
                    $gapselectoptions .= " || " . $answer->answer;
                }
                if (trim($answer->answer) != '') {
                    if ($solutions) {
                        // Solution.
                        // In the order type value size style.
                        $replacearray["$i"] = "<input type=\"text\"" .
                            " value=\"" . $answer->answer . "\"" .
                            " size=\"" . $size . "\"" .
                            " style=\"border: 1px dashed #000000; height: 24px;\"" .
                            "/>&#160;<sup>*" . $answer->feedback . "</sup>\n";
                    } else {
                        // solution.
                        // In the order type value size style.
                        $spacesize = "";
                        for ($j = 1; $j <= $size; $j++) {
                            $spacesize .= "&#160;&#160;";
                        }
                        $fieldsarray[$i] = "<input type=\"text\" size=\"" . $size .
                            "\" style=\"border: 1px dashed #000000; height: 24px;\" value=\"\"/>";
                        $replacearray["$i"] = "<input type=\"text\"" .
                            " value=\"" . $spacesize . "\"" .
                            " size=\"" . $size . "\"" .
                            " style=\"border: 1px dashed #000000; height: 24px;\"" .
                            "/>&#160;<sup>*" . $answer->feedback . "</sup>\n";
                    }
                }
                $i++;
                // Next answer.
            }

            foreach ($gapselectoptionoptions as $option => $optiontext) {
                // Gapselectoptionsoptions in the form {"1":"cat || mat","2":"once || many"}.
                foreach ($gapoptionsarray as $gapoption => $gap) {
                    // Gapoptionsarray in the form [{"1":1},{"1":2},{"2":3},{"2":4}].
                    if (array_key_exists($optiontext,
                        $annotationsarray)) {
                        $annotationsarray[$optiontext][] = (int)$gap;
                    } else {
                        // Only if it doesn't already exist.
                        $annotationsarray[$optiontext] = array((int)$gap);
                    }
                }
            }
        }

        // Routine to sort out multiple identical option fields.
        $singleannotationcounter = 1;
        $annotation = "";

        foreach ($annotationsarray as $uniqueannotation => $annotationvalues) {
            foreach ($annotationvalues as $annotationfield => $annotationgap) {
                $replacearray["#" . $annotationgap] = $fieldsarray[$annotationgap] . "&#160;<sup>*" . $singleannotationcounter .
                    "</sup>\n";
            }
            // Shuffle $uniqueannotation.
            $uniqueannotationelements = explode(' || ', $uniqueannotation);
            shuffle($uniqueannotationelements);
            $annotation .= "<sup>*" . $singleannotationcounter . "</sup>&#160;" . get_string('options', 'quiz_solution') .
                ": " . implode(' || ', $uniqueannotationelements) . "<br />\n";
            $singleannotationcounter++;
        }
        $annotation = "<p>" . $annotation. "</p>\n";
    }

    /**
     * Process the kprime question type.
     *
     * @param object $questiondata the data defining a kprime question.
     * @param bool $solutions whether to show the solutions.
     */
    public function processkprimequestion($questiondata, $solutions = false) {
        // Kprime question type.
        global $CFG, $DB, $questiontext, $replacearray;
        $kprimeoptionnumbering = 0;
        $kprimeoptions = array();
        $kprimeoptionstring = "";
        $kprimeresponse1 = "";
        $kprimeresponse2 = "";
        if (get_class($questiondata) == 'stdClass') {
            foreach ($questiondata->options->rows as $answer) {
                // Remove outer <p> </p>.
                $kprimeoptions[] = preg_replace('/<p[^>]*>(.*)<\/p[^>]*>/', '$1',
                    $answer->optiontext);
            }
        }

        $kprimeresponse1 = $questiondata->responsetext_1;
        $kprimeresponse2 = $questiondata->responsetext_2;

        if (isset($questiondata->options->shuffleanswers)) {
            if ($questiondata->options->shuffleanswers == 1) {
                // Shuffle the array.
                shuffle($kprimeoptions);
            }
        }
        foreach ($kprimeoptions as $kprimeoption) {
            if ($kprimeoptionnumbering == 0) {
                $kprimeoptionstring .= "<p>$kprimeresponse1&#160;<input type=\"checkbox\" />&#160;&#160;" .
                    "<input type=\"checkbox\" />&#160;$kprimeresponse2&#160;&#160;&#160;&#160;&#160;&#160;" .
                    $kprimeoption . "</p>\n";
            } else {
                $kprimeoptionstring .= "<p>$kprimeresponse1&#160;<input type=\"checkbox\" />&#160;&#160;" .
                    "<input type=\"checkbox\" />&#160;$kprimeresponse2&#160;&#160;&#160;&#160;&#160;&#160;" .
                    $kprimeoption . "</p>\n";
            }
            $kprimeoptionnumbering++;
        }
        $questiontext .= $kprimeoptionstring;
    }

    /**
     * Process the mtf question type.
     *
     * @param object $questiondata the data defining a mtf question.
     * @param bool $solutions whether to show the solutions.
     */
    public function processmtfquestion($questiondata, $solutions = false) {
        // Multiple True/False question type.
        global $CFG, $DB, $questiontext, $replacearray;
        $mtfoptionnumbering = 0;
        $mtfoptions = array();
        $mtfoptionstring = "";
        $mtfresponse1 = "";
        $mtfresponse2 = "";
        if (get_class($questiondata) == 'stdClass') {
            foreach ($questiondata->options->rows as $answer) {
                // Remove outer <p> </p>.
                $mtfoptions[] = preg_replace('/<p[^>]*>(.*)<\/p[^>]*>/', '$1',
                    $answer->optiontext);
            }
        }

        $mtfresponse1 = $questiondata->responsetext_1;
        $mtfresponse2 = $questiondata->responsetext_2;

        if (isset($questiondata->options->shuffleanswers)) {
            if ($questiondata->options->shuffleanswers == 1) {
                // Shuffle the array.
                shuffle($mtfoptions);
            }
        }
        foreach ($mtfoptions as $mtfoption) {
            if ($mtfoptionnumbering == 0) {
                $mtfoptionstring .= "<p>$mtfresponse1&#160;<input type=\"checkbox\" />&#160;&#160;" .
                    "<input type=\"checkbox\" />&#160;$mtfresponse2&#160;&#160;&#160;&#160;&#160;&#160;" . $mtfoption . "</p>\n";
            } else {
                $mtfoptionstring .= "<p>$mtfresponse1&#160;<input type=\"checkbox\" />&#160;&#160;" .
                    "<input type=\"checkbox\" />&#160;$mtfresponse2&#160;&#160;&#160;&#160;&#160;&#160;" . $mtfoption . "</p>\n";
            }
            $mtfoptionnumbering++;
        }
        $questiontext .= $mtfoptionstring;
    }

    /**
     * Process the drag and drop into text question type.
     *
     * The *1 asterisk with number comes in when there are more than one groups.
     *
     * @param object $questiondata the data defining a drag and drop into text question.
     * @param bool $solutions whether to show the solutions.
     * @throws coding_exception
     */
    public function processddwtosquestion($questiondata, $solutions = false) {
        // Drag and drop into text question type.
        global $questiontext, $replacearray, $annotation, $annotationnumbering;
        $ddwtosoptionnumbering = 0;
        $ddwtosoptionstring = "";
        $ddwtosoptions = array();
        // Input field should be at least size 3.
        $size = 3;
        if (get_class($questiondata) == 'stdClass') {
            $i = 1;
            foreach ($questiondata->options->answers as $answer) {
                // Looking for the largest option.
                if (trim($answer->answer) != '') {
                    $answeroption = $answer->answer;
                    if (strlen((string)$answeroption) > $size) {
                        $size = strlen((string)$answeroption);
                    }
                }
            }
            foreach ($questiondata->options->answers as $answer) {
                $ddwtosoptions[] = $answer->answer;
                if (trim($answer->answer) != '') {
                    $questiontext .= "<p>";
                    // Remove outer <p> </p>.
                    $questiontext .= preg_replace('/<p[^>]*>(.*)<\/p[^>]*>/', '$1',
                        $answer->answer);
                    $questiontext .= "</p>\n";
                    if ($solutions) {
                        // Solution.
                        // In the order type value size style.
                        $replacearray["#$i"] = "<input type=\"text\"" .
                            " value=\"" . $answer->answer . "\"" .
                            " size=\"" . $size . "\"" .
                            " style=\"border: 1px dashed #000000; height: 24px;\"" .
                            "/>&#160;<sup>*" . $i . "</sup>\n";
                    } else {
                        // solution.
                        // In the order type value size style.
                        $spacesize = "";
                        for ($j = 1; $j <= $size; $j++) {
                            $spacesize .= "&#160;&#160;";
                        }
                        $replacearray["$i"] = "<input type=\"text\"" .
                            " value=\"" . $spacesize . "\"" .
                            " size=\"" . $size . "\"" .
                            " style=\"border: 1px dashed #000000; height: 24px;\"" .
                            "/>&#160;<sup>*" . $annotationnumbering . "</sup>\n";
                    }
                }

                $i++;
                // Next answer.
            }
            if ($questiondata->options->shuffleanswers == 1) {
                // Shuffle the array.
                shuffle($ddwtosoptions);
            }
        }
        if (get_class($questiondata) == 'qtype_ddwtos_question') {
            foreach ($questiondata->choices as $choice) {
                // Looking for the largest option.
                if (trim($choice) != '') {
                    if (strlen((string)$choice) > $size) {
                        $size = strlen((string)$choice);
                    }
                }
            }
            foreach ($questiondata->stems as $question) {
                if (trim($question) != '') {
                    $questiontext .= "<p>";
                    // Remove outer <p> </p>.
                    $questiontext .= preg_replace('!^<p>(.*?)</p>$!i', '$1', $question);
                    $questiontext .= "\n";
                    $questiontext .= "&#160;<input type=\"text\" size=\"" . $size .
                        "\" style=\"border: 1px dashed #000000; height: 24px;\"/>&#160;<sup>*</sup></p>\n";
                }
            }
            foreach ($questiondata->choices as $choice) {
                $ddwtosoptions[] = $choice;
            }
            if ($questiondata->shufflestems = 1) {
                // Shuffle the array.
                shuffle($ddwtosoptions);
            }
        }

        foreach ($ddwtosoptions as $ddwtosoption) {
            if ($ddwtosoptionnumbering == 0) {
                $ddwtosoptionstring .= $ddwtosoption;
            } else {
                $ddwtosoptionstring .= " || " . $ddwtosoption;
            }
            $ddwtosoptionnumbering++;
        }
        $annotation = "<sup>*</sup>&#160;" . get_string('options', 'quiz_solution') . ": " .
            $ddwtosoptionstring . "\n";
    }

    /**
     * This method needs to be called before the ->excludedqtypes and
     *      ->manualqtypes fields can be used.
     *
     * Taken from /question/type/random/questiontype.php.
     */
    protected function init_qtype_lists() {
        if (!is_null($this->excludedqtypes)) {
            return; // Already done.
        }
        $excludedqtypes = array();
        $manualqtypes = array();
        foreach (question_bank::get_all_qtypes() as $qtype) {
            $quotedname = "'" . $qtype->name() . "'";
            if (!$qtype->is_usable_by_random()) {
                $excludedqtypes[] = $quotedname;
            } else if ($qtype->is_manual_graded()) {
                $manualqtypes[] = $quotedname;
            }
        }
        $this->excludedqtypes = implode(',', $excludedqtypes);
        $this->manualqtypes = implode(',', $manualqtypes);
    }

    /**
     * Load the definition of another question picked randomly by this question.
     *
     * @param object $questiondata the data defining a random question.
     * @param array $excludedquestions of question ids. We will no pick any
     *      question whose id is in this list.
     * @param bool $allowshuffle if false, then any shuffle option on the
     *      selected quetsion is disabled.
     * @return null|question_definition the definition of the question that was
     *      selected, or null if no suitable question could be found.
     */
    public function choose_other_question($questiondata, $excludedquestions, $allowshuffle = true) {
        $available = $this->get_available_questions_from_category($questiondata->category, !empty($questiondata->questiontext));
        shuffle($available);

        foreach ($available as $questionid) {
            if (in_array($questionid, $excludedquestions)) {
                continue;
            }

            $question = question_bank::load_question($questionid, $allowshuffle);

            return $question;
        }
        return null;
    }

    /**
     * Get all the usable questions from a particular question category.
     *
     * taken from /question/type/random/questiontype.php
     *
     * @param int $categoryid the id of a question category.
     * @param bool $subcategories whether to include questions from subcategories.
     * @return array of question records.
     * @throws coding_exception
     */
    public function get_available_questions_from_category($categoryid, $subcategories) {
        if (isset($this->availablequestionsbycategory[$categoryid][$subcategories])) {
            return $this->availablequestionsbycategory[$categoryid][$subcategories];
        }

        $this->init_qtype_lists();
        if ($subcategories) {
            $categoryids = question_categorylist($categoryid);
        } else {
            $categoryids = array($categoryid);
        }

        $questionids = question_bank::get_finder()->get_questions_from_categories(
            $categoryids, 'qtype NOT IN (' . $this->excludedqtypes . ')');
        $this->availablequestionsbycategory[$categoryid][$subcategories] = $questionids;
        return $questionids;
    }

    /**
     * This function get the dataset items using id as unique parameter and return an
     * array with itemnumber as index sorted ascendant
     * If the multiple records with the same itemnumber exist, only the newest one
     * i.e with the greatest id is used, the others are ignored but not deleted.
     * MDL-19210
     *
     * taken from /question/type/calculated/questiontype.php
     *
     * @param int $definition the definition id.
     * @return array
     * @throws dml_exception
     */
    public function get_database_dataset_items($definition) {
        global $DB;
        $databasedataitems = $DB->get_records_sql(
            // Use number as key!!
            " SELECT id , itemnumber, definition,  value
            FROM {question_dataset_items}
            WHERE definition = $definition order by id DESC ", array($definition));
        $dataitems = Array();
        foreach ($databasedataitems as $id => $dataitem) {
            if (!isset($dataitems[$dataitem->itemnumber])) {
                $dataitems[$dataitem->itemnumber] = $dataitem;
            }
        }
        ksort($dataitems);
        return $dataitems;
    }

    /**
     * Get the name table.
     *
     * @return string the name table
     * @throws coding_exception
     */
    public function get_name_table() {
        return "<p>&#160;</p>\n<table>\n" .
            "<tr><td>" . get_string("lastname", "moodle") .
            "</td><td>&#160;&#160;...........................................</td></tr>\n" .
            "<tr><td>" . get_string("firstname", "moodle") .
            "</td><td>&#160;&#160;...........................................</td></tr>\n" .
            "<tr><td>" . get_string("username", "moodle") .
            "</td><td>&#160;&#160;...........................................</td></tr>\n" .
            "</table>\n" .
            "<br />" . "\n";
    }

    /**
     * Get the quiz title.
     *
     * @param object $quiz this quiz.
     * @return string the quiz title to display or print to doc.
     * @throws coding_exception
     */
    public function get_quiz_title($quiz) {
        global $OUTPUT;
        return $OUTPUT->heading(format_string($quiz->name));
    }

    /**
     * Return a placeholder
     * Surrounded by curly brackets.
     *
     * It could be arriving contents of {base} {a} calculated questions placeholders
     * or {#1} {#2} cloze questions placeholders.
     *
     * @param string $val the "#1", "#2" or "1", "2" needles.
     * @return string $val surrounded by curly brackets.
     */
    public function placeholders($val) {
        return '/\{' . $val . '}/';
    }

    /**
     * Return a placeholder
     * Surrounded by double square brackets.
     *
     * It could be arriving contents of [[1]] gapselect questions placeholders.
     *
     * @param string $val the "1", "2" needles.
     * @return string $val surrounded by square brackets.
     */
    public function squareplaceholders($val) {
        return '/\[\[' . $val . ']]/';
    }
}
