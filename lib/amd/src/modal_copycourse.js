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
 * @module     core/modal_copycourse
 * @class      modal_copycourse
 * @package    core
 * @copyright  2016 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/modal_factory'], function($, ModalFactory) {
  var trigger = $('.action-copy');
  ModalFactory.create({
    title: M.util.get_string('copycourse', 'moodle'),
    body: '<p>test body content</p>',
    footer: '<button type="button" class="btn btn-primary" data-action="login">' + M.util.get_string('copy', 'moodle') +
        '</button> <button type="button" class="btn btn-secondary" data-action="cancel">' +
        M.util.get_string('cancel', 'moodle') + '</button>',
    }, trigger)
    .done(function(modal) {
      // Do what you want with your new modal.
    });
});
