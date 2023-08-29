import { Plugin, icons } from 'ckeditor5/src/core';
import { ButtonView } from "ckeditor5/src/ui";
import { WidgetToolbarRepository, isWidget } from 'ckeditor5/src/widget';

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

  init() {
    const editor = this.editor;
    const options = editor.config.get('oembedEntities');

    editor.ui.componentFactory.add('oembedEntityEdit', (locale) => {
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: editor.t('Edit'),
        icon: icons.pencil,
        tooltip: true,
      });

      this.listenTo(buttonView, 'execute', () => {
        const element = editor.model.document.selection.getSelectedElement();
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

}
