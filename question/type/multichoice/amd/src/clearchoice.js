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
 * Manages 'Clear my choice' links for multichoice questions with a single correct answer.
 *
 * @module     qtype_multichoice/clearchoice
 * @class      clearchoice
 * @package    qtype_multichoice
 * @copyright  2017 Luca Bösch <luca.boesch@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.4
 */
define(['jquery'], function($) {
    return {
        /**
         * Init function.
         *
         */
        init: function() {
            $(document).ready(function() {
                  // Attach a delegated event handler
                $(".answer").on("click", "input[name$='_answer']", function() {
                    if ($(this).attr('id').indexOf("answerclear") >= 0) {
                        $(this).closest('div.answer').removeClass("qtype_multichoice_answer-selected");
                    } else {
                        $(this).closest('div.answer').addClass("qtype_multichoice_answer-selected");
                    }
                });
            });
        }
    };
});
