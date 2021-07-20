/**
 * @file
 * Entity oEmbed plugin.
 */

(function ($, Drupal, CKEDITOR) {

  'use strict';

  CKEDITOR.plugins.add('oe_oembed_entities', {
    // This plugin requires the Widgets System defined in the 'widget' plugin.
    requires: 'widget',

    beforeInit: function (editor) {

      // Generic command for adding entities.
      editor.addCommand('edit_entities', {
        allowedContent: 'p[data-oembed]',
        requiredContent: 'p[data-oembed]',
        modes: { wysiwyg: 1 },
        canUndo: true,
        exec: function (editor, data) {
          var dialogSettings = {
            dialogClass: 'oe-oembed-entities-select-dialog',
            resizable: false
          };

          var saveCallback = function (values) {
            var attributes = values.attributes;
            var inline = Boolean(attributes['data-embed-inline']);

            if (inline) {
              createAndEmbedInlineElement(editor, attributes);
            }
            else {
              createAndEmbedBlockElement(editor, attributes);
            }
          };

          var embed_button_id = data.id;

          // Open the dialog to look for an entity.
          Drupal.ckeditor.openDialog(editor, Drupal.url('oe-oembed-embed/dialog/' + editor.config.drupal.format + '/' + embed_button_id), {}, saveCallback, dialogSettings);
        }
      });

      // Register the entity embed widget.
      editor.widgets.add('oe_oembed_entities', {
        allowedContent: 'p[data-oembed]',
        requiredContent: 'p[data-oembed]',

        // Upcasts the embedded element to be treated as a widget by CKEditor.
        upcast: function (element, data) {
          var attributes = element.attributes;
          if (typeof attributes['data-oembed'] === 'undefined') {
            return;
          }

          // Generate an ID for the element, so that we can use the Ajax
          // framework later when we need to render inside the init() method.
          element.attributes.id = generateEmbedId();
          return element;
        },

        // Fetch the rendered entity.
        init: function () {
          // Do nothing for now.
        },

        // Downcast the element.
        downcast: function (element) {
          // Remove the auto-generated ID.
          delete element.attributes.id;
          return element;
        }
      });

      // Register the toolbar button.
      if (editor.ui.addButton) {
        for (var key in editor.config.Oembed_buttons) {
          var button = editor.config.Oembed_buttons[key];
          editor.ui.addButton(button.id, {
            label: button.label,
            data: button,
            allowedContent: 'p[!data-oembed]',
            click: function (editor) {
              editor.execCommand('edit_entities', this.data);
            },
            icon: button.image,
          });
        }
      }
    }
  });

  /**
   * Generates unique HTML IDs for the widgets.
   *
   * @return {string}
   *   A unique HTML ID.
   */
  function generateEmbedId() {
    if (typeof generateEmbedId.counter === 'undefined') {
      generateEmbedId.counter = 0;
    }
    return 'oe-embed-embed-' + generateEmbedId.counter++;
  }

  /**
   * Creates and inserts into the editor the <p> based embed code.
   *
   * @param editor
   *   The editor.
   * @param attributes
   *   The array of attributes.
   */
  function createAndEmbedBlockElement(editor, attributes) {
    var entityElement = editor.document.createElement('p');
    for (var key in attributes) {
      if (['data-resource-url', 'data-resource-label', 'data-embed-inline'].includes(key)) {
        continue;
      }
      entityElement.setAttribute(key, attributes[key]);
    }

    var childElement = editor.document.createElement('a');
    childElement.setAttribute('href', attributes['data-resource-url']);
    childElement.setHtml(attributes['data-resource-label']);
    entityElement.setHtml(childElement.getOuterHtml());
    editor.insertHtml(entityElement.getOuterHtml() + '<p></p>');
  }

  /**
   * Creates and inserts into the editor the <a> based embed code.
   *
   * @param editor
   *   The editor.
   * @param attributes
   *   The array of attributes.
   */
  function createAndEmbedInlineElement(editor, attributes) {
    var entityElement = editor.document.createElement('a');
    for (var key in attributes) {
      if (['data-resource-url', 'data-resource-label', 'data-embed-inline'].includes(key)) {
        continue;
      }
      entityElement.setAttribute(key, attributes[key]);
    }


    entityElement.setAttribute('href', attributes['data-resource-url']);
    entityElement.setHtml(attributes['data-resource-label']);
    editor.insertHtml(entityElement.getOuterHtml());
  }

})(jQuery, Drupal, CKEDITOR);
