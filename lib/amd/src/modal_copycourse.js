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
define(['jquery', 'core/notification', 'core/custom_interaction_events', 'core/modal', 'core/modal_registry'],
  function($, Notification, CustomEvents, Modal, ModalRegistry) {

    var registered = false;
    var SELECTORS = {
      COPY_BUTTON: '[data-action="copy"]',
      CANCEL_BUTTON: '[data-action="cancel"]',
    };

    /**
     * Constructor for the Modal.
     *
     * @param {object} root The root jQuery element for the modal
     */
    var CopyCourse = function(root) {
      Modal.call(this, root);

      if (!this.getFooter().find(SELECTORS.COPY_BUTTON).length) {
        Notification.exception({message: 'No copy button found'});
      }

      if (!this.getFooter().find(SELECTORS.CANCEL_BUTTON).length) {
        Notification.exception({message: 'No cancel button found'});
      }
    };

    CopyCourse.TYPE = 'core-copycourse';
    CopyCourse.prototype = Object.create(Modal.prototype);
    CopyCourse.prototype.constructor = CopyCourse;

    /**
     * Set up all of the event handling for the modal.
     *
     * @method registerEventListeners
     */
    CopyCourse.prototype.registerEventListeners = function() {
      // Apply parent event listeners.
      Modal.prototype.registerEventListeners.call(this);

      this.getModal().on(CustomEvents.events.activate, SELECTORS.COPY_BUTTON, function(e, data) {
        // Add your logic for when the login button is clicked. This could include the form validation,
        // loading animations, error handling etc.
      }.bind(this));

      this.getModal().on(CustomEvents.events.activate, SELECTORS.CANCEL_BUTTON, function(e, data) {
        // Add your logic for when the cancel button is clicked.
      }.bind(this));
    };

    // Automatically register with the modal registry the first time this module is imported so that you can create modals
    // of this type using the modal factory.
    if (!registered) {
      ModalRegistry.register(CopyCourse.TYPE, CopyCourse, 'core/modal_copycourse');
      registered = true;
    }

    return CopyCourse;
  });