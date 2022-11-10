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

          var existing_element = getSelectedEmbeddedEntity(editor);

          var existing_values = {};
          if (existing_element && existing_element.$ && existing_element.$.firstChild) {
            var embed_dom_element = existing_element.$.firstChild;
            // Populate array with the entity's current attributes.
            var attribute = null, attributeName;
            for (var key = 0; key < embed_dom_element.attributes.length; key++) {
              attribute = embed_dom_element.attributes.item(key);
              attributeName = attribute.nodeName.toLowerCase();
              if (attributeName === 'data-display-as' || attributeName === 'data-oembed') {
                existing_values[attributeName] = attribute.nodeValue;
              }
            }
          }

          var saveCallback = function (values) {
            var attributes = values.attributes;
            var inline = Boolean(attributes['data-embed-inline']);

            if (inline) {
              createAndEmbedInlineElement(editor, attributes);
            }
            else {
              createAndEmbedBlockElement(editor, attributes, Boolean(existing_element));
            }

            if (existing_element) {
              existing_element.remove();
            }
          };

          var embed_button_id = data.id;
          if (!embed_button_id && existing_element) {
            // When we are editing the existing embed, we don't have the
            // embed button so we need to determine a default one: any which
            // is configured to use the embedded entity type should suffice
            // as we can end up loading the entity by just the UUID and move to
            // the second dialog.
            var oembed = existing_values['data-oembed'];

            Object.keys(editor.config.Oembed_default_buttons).forEach(function (entity_type) {
              if (oembed.includes('/' + entity_type + '/')) {
                embed_button_id = editor.config.Oembed_default_buttons[entity_type];
              }
            })

          }
          var extra_values = {
            'current_route': editor.config.current_route,
            'current_route_parameters': editor.config.current_route_parameters,
            ...existing_values
          };

          // Open the dialog to look for an entity.
          Drupal.ckeditor.openDialog(editor, Drupal.url('oe-oembed-embed/dialog/' + editor.config.drupal.format + '/' + embed_button_id), extra_values, saveCallback, dialogSettings);
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

      // Execute widget editing action on double click.
      editor.on('doubleclick', function (evt) {
        var element = getSelectedEmbeddedEntity(editor) || evt.data.element;

        if (isEditableEntityWidget(editor, element)) {
          editor.execCommand('edit_entities');
        }
      });
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
   * @param existing
   *   Whether the element is being replaced.
   */
  function createAndEmbedBlockElement(editor, attributes, existing = false) {
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
    var suffix = String('');
    if (!existing) {
      suffix = '<p></p>';
    }
    editor.insertHtml(entityElement.getOuterHtml() + suffix);
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

  /**
   * Get the surrounding drupalentity widget element.
   *
   * @param {CKEDITOR.editor} editor
   */
  function getSelectedEmbeddedEntity(editor) {
    var selection = editor.getSelection();
    var selectedElement = selection.getSelectedElement();
    if (isEditableEntityWidget(editor, selectedElement)) {
      return selectedElement;
    }

    return null;
  }

  /**
   * Checks if the given element is an editable widget.
   *
   * @param {CKEDITOR.editor} editor
   * @param {CKEDITOR.htmlParser.element} element
   */
  function isEditableEntityWidget (editor, element) {
    var widget = editor.widgets.getByElement(element, true);
    if (!widget || widget.name !== 'oe_oembed_entities') {
      return false;
    }

    return true;
  }

})(jQuery, Drupal, CKEDITOR);
