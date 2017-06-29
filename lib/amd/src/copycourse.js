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
 * Show an add block modal instead of doing it on a separate page.
 *
 * @module     core/copycourse
 * @class      copycourse
 * @package    core
 * @copyright  2016 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/templates', 'core/modal_factory', 'core/modal_copycourse'], function($, Templates, ModalFactory, CopyCourse) {
    return /** @alias module:core/copycourse */ {
        /**
         * Global init function for this module.
         *
         * @method init
         */
        init: function() {
            var trigger = $('.action-copy');
            ModalFactory.create({type: CopyCourse.TYPE}, trigger);
        }
    };
});