/**
 * @file
 * Provides JavaScript additions to entity embed dialog.
 *
 * This file provides popup windows for previewing embedded entities from the
 * embed dialog.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Attach behaviors to links for entities.
   */
  Drupal.behaviors.oEmbedPreviewEntities = {
    attach: function (context) {
      $(context).find('form.oe-oembed-entity-dialog .form-item-entity a').on('click', Drupal.oEmbedDialog.openInNewWindow);
    },
    detach: function (context) {
      $(context).find('form.oe-oembed-entity-dialog .form-item-entity a').off('click', Drupal.oEmbedDialog.openInNewWindow);
    }
  };

  /**
   * Behaviors for the oEmbedDialog iframe.
   */
  Drupal.behaviors.oEmbedDialog = {
    attach: function (context, settings) {
      $(once('js-oe-oembed-entity-dialog', 'body')).on('entityBrowserIFrameAppend', function () {
        $('.oe-oembed-entities-select-dialog').trigger('resize');
        // Hide the next button, the click is triggered by Drupal.oEmbedDialog.selectionCompleted.
        $('#drupal-modal').parent().find('.js-button-next').addClass('visually-hidden');
      });
    }
  };

  /**
   * Entity embed dialog utility functions.
   */
  Drupal.oEmbedDialog = Drupal.oEmbedDialog || {

    /**
     * Open links to entities within forms in a new window.
     *
     * @param {jQuery.Event} event
     *   The click event.
     */
    openInNewWindow: function (event) {
      event.preventDefault();
      $(this).attr('target', '_blank');
      window.open(this.href, 'entityPreview', 'toolbar=0,scrollbars=1,location=1,statusbar=1,menubar=0,resizable=1');
    },

    /**
     * Handle completion of selection.
     *
     * @param {jQuery.Event} event
     *   The click event.
     * @param {string} uuid
     *  The UUID.
     * @param {array} entities
     *   Array of selected entities.
     */
    selectionCompleted: function (event, uuid, entities) {
      $('.oe-oembed-entities-select-dialog .js-button-next').click();
    }
  };

})(jQuery, Drupal);
