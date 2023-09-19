import { Plugin, icons } from 'ckeditor5/src/core';
import { ButtonView } from "ckeditor5/src/ui";
import { WidgetToolbarRepository, isWidget } from 'ckeditor5/src/widget';
import { openDialog } from "./utils";

export default class OembedToolbar extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [WidgetToolbarRepository];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'OembedToolbar';
  }

  /**
   * @inheritdoc
   */
  init() {
    const editor = this.editor;
    const options = editor.config.get('oembedEntities');
    const { dialogSettings = {}, defaultButtons, currentRoute, currentRouteParameters } = options;
    const oembedEntitiesEditing = this.editor.plugins.get('OembedEntitiesEditing');

    editor.ui.componentFactory.add('oembedEntityEdit', (locale) => {
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: editor.t('Edit'),
        icon: icons.pencil,
        tooltip: true,
      });

      this.listenTo(buttonView, 'execute', () => {
        const element = editor.model.document.selection.getSelectedElement();
        const buttonId = this._getButtonId(element, defaultButtons);
        const libraryUrl = Drupal.url('oe-oembed-embed/dialog/' + options.format + '/' + buttonId);

        let existingValues = {
          'current_route': currentRoute,
          'current_route_parameters': currentRouteParameters,
        };
        for (const [modelAttribute, dataAttribute] of Object.entries(oembedEntitiesEditing.viewAttrs)) {
          if (element.hasAttribute(modelAttribute)) {
            existingValues[dataAttribute] = element.getAttribute(modelAttribute);
          }
        }

        openDialog(
          libraryUrl,
          existingValues,
          ({ attributes }) => {
            // Our module doesn't support sending the button from the response.
            attributes['data-button-id'] = buttonId;
            editor.execute('oembedEntities', attributes);
          },
          dialogSettings
        );
      });


      return buttonView;
    });
  }

  /**
   * @inheritdoc
   */
  afterInit() {
    const { editor } = this;
    const widgetToolbarRepository = editor.plugins.get(WidgetToolbarRepository);

    widgetToolbarRepository.register('oembed', {
      ariaLabel: Drupal.t('OpenEuropa Oembed toolbar'),
      items: ['oembedEntityEdit'],
      // Get the selected image or an image containing the figcaption with the selection inside.
      getRelatedElement: (selection) => {
        const selectedElement = selection.getSelectedElement();

        if (selectedElement && isWidget(selectedElement) && !!selectedElement.getCustomProperty('OembedEntity')) {
          return selectedElement;
        }

        return null;
      },
    });
  }

  /**
   * Returns the button ID related to a specific element.
   *
   * @param {module:engine/model/element~Element} element
   *   The selected element.
   * @param {object} buttons
   *   The default buttons.
   *
   * @return {string}
   *   The button ID.
   */
  _getButtonId(element, buttons) {
    if (element.hasAttribute('oembedEntitiesButtonId')) {
      return element.getAttribute('oembedEntitiesButtonId');
    }

    const oembedUrl = element.getAttribute('oembedEntitiesOembed');
    const key = Object.keys(buttons).find(entityType => {
      return oembedUrl.includes(`/${entityType}/`);
    });

    return buttons[key];
  }

}
