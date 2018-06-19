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
 * Provides a word and character count to a essay question textarea.
 *
 * @module     qtype_essay/wordcount
 * @copyright  2018 Luca Bösch <luca.boesch@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/str',
    'qtype_essay/wordcount'
],
       function(
           $,
           Str
       ) {

           return {
               ctx: {},
               wc_done: function(transactionid, response) {
                   var jsondata = Y.JSON.parse(response.responseText);
                   self = this;
                   $.each(jsondata,function(key,value) {
                       words = value.words;
                       characters = value.characters;
                       self.set_wordcount(key, characters, words);
                   });
                   this.in_flight = false;
               },
               wc_failed: function() {
                   this.in_flight = false;
                   alert("failed");
               },
               set_wordcount: function(key, chars, words) {
                   var count = key + ': ' + M.util.get_string('words', 'qtype_essay') + ': ' + words + ' / ' + this.ctx[key].wordlimit + '<br />' +
                       M.util.get_string('characters', 'qtype_essay') + ': ' + chars + ' / ' + this.ctx[key].charlimit;
                   $(document.getElementById(key + '_wordcount')).html(count);
               },
               update_wordcount: function() {
                   this.in_flight = Y.io("/essay/question/type/essay/wc.ajax.php", {form: document.getElementById('responseform'), method: "POST", on: { success: this.wc_done, failure: this.wc_failed}, context: this});
               },
               init: function($params) {
                   this.ctx[$params.editorname] = $params;
                   if(!this.in_flight)
                       this.update_wordcount();
               }
           };
       });
