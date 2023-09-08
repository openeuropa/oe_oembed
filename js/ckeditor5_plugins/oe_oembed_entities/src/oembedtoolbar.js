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
    const { dialogSettings = {}, defaultButtons = {} } = options;
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

        let existingValues = {};
        for (const [modelAttribute, dataAttribute] of Object.entries(oembedEntitiesEditing.viewAttrs)) {
          if (element.hasAttribute(modelAttribute)) {
            existingValues[dataAttribute] = element.getAttribute(modelAttribute);
          }
        }

        this._openDialog(
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
   * Open a dialog for a Drupal-based plugin.
   *
   * Copy of Drupal.ckeditor5.openDialog, with addition of existingValues parameter.
   *
   * @param {string} url
   *   The URL that contains the contents of the dialog.
   * @param {object} existingValues
   *   Existing values that will be sent via POST to the url for the dialog
   *  contents.
   * @param {function} saveCallback
   *   A function to be called upon saving the dialog.
   * @param {object} dialogSettings
   *   An object containing settings to be passed to the jQuery UI.
   */
  _openDialog(url, existingValues, saveCallback, dialogSettings) {
    // Add a consistent dialog class.
    const classes = dialogSettings.dialogClass
      ? dialogSettings.dialogClass.split(' ')
      : [];
    classes.push('ui-dialog--narrow');
    dialogSettings.dialogClass = classes.join(' ');
    dialogSettings.autoResize =
      window.matchMedia('(min-width: 600px)').matches;
    dialogSettings.width = 'auto';

    const ckeditorAjaxDialog = Drupal.ajax({
      dialog: dialogSettings,
      dialogType: 'modal',
      selector: '.ckeditor5-dialog-loading-link',
      url,
      progress: { type: 'fullscreen' },
      submit: {
        editor_object: existingValues,
      },
    });
    ckeditorAjaxDialog.execute();

    // Store the save callback to be executed when this dialog is closed.
    Drupal.ckeditor5.saveCallback = saveCallback;
  }

  /**
   * Returns the button ID related to a specific element.
   *
   * @param {object} element
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
