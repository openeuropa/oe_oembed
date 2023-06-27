/**
 * @file registers the simpleBox toolbar button and binds functionality to it.
 */

import {Plugin} from 'ckeditor5/src/core';
import {ButtonView, ContextualBalloon, clickOutsideHandler} from 'ckeditor5/src/ui';
import { isWidget } from 'ckeditor5/src/widget';
import OembedEntitiesActionsView from './ui/oembedentitiesactionsview';
import defaultIcon from '../../../../icons/embed.svg';

export default class OembedEntitiesUI extends Plugin {

  static get requires() {
    return [ ContextualBalloon ];
  }

  init() {
    const editor = this.editor;
    const command = editor.commands.get('oembedEntities');
    const options = editor.config.get('oembedEntities');
    const { dialogSettings = {} } = options;

    if (!options) {
      return;
    }

    Object.keys(options.buttons).forEach(id => {
      const libraryUrl = Drupal.url('oe-oembed-embed/dialog/' + options.format + '/' + id);
      editor.ui.componentFactory.add(id, (locale) => {
        const button = options.buttons[id];
        const buttonView = new ButtonView(locale);

        let icon = null;
        if (button.icon.endsWith('svg')) {
          let XMLrequest = new XMLHttpRequest();
          XMLrequest.open('GET', button.icon, false);
          XMLrequest.send(null);
          icon = XMLrequest.response;
        }

        // Create the toolbar button.
        buttonView.set({
          label: button.label,
          icon: icon ?? defaultIcon,
          tooltip: true,
        });

        // Bind the state of the button to the command.
        buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

        // Execute the command when the button is clicked (executed).
        this.listenTo(buttonView, 'execute', () =>
          //editor.execute('oembedEntities', {
          //  'data-display-as': 'image_teaser',
          //  'data-oembed': 'https://oembed.ec.eu'
          //}),
          Drupal.ckeditor5.openDialog(
            libraryUrl,
            ({ attributes }) => {
              editor.execute('oembedEntities', attributes);
            },
            dialogSettings,
          ),
        );

        return buttonView;
      });
    });

    this._balloon = editor.plugins.get(ContextualBalloon);
    this.actionsView = new OembedEntitiesActionsView(editor.locale);
    // Execute unlink command after clicking on the "Unlink" button.
    this.listenTo(this.actionsView, 'edit', () => {
      debugger;
      editor.execute('oembedEntities', {
        'data-display-as': 'image_teaser',
        'data-oembed': 'https://oembed.ec.eu'
      });
      this._hideUI();
    });
    // Close the panel on esc key press when the **actions have focus**.
    editor.keystrokes.set('Esc', (data, cancel) => {
      this._hideUI();
      cancel();
    });

    this.listenTo(editor.ui, 'update', () => {
      if (!this._getSelectedOembedWidget()) {
        this._hideUI();
      }
    });

    // Close on click outside of balloon panel element.
    clickOutsideHandler({
      emitter: this.actionsView,
      activator: () => this._isViewInBalloon,
      contextElements: [this._balloon.view.element],
      callback: () => this._hideUI()
    });

    const viewDocument = editor.editing.view.document;
    // Handle click on view document and show panel when selection is placed inside the link element.
    // Keep panel open until selection will be inside the same link element.
    this.listenTo(viewDocument, 'click', () => {
      if (this._getSelectedOembedWidget()) {
        this._showUI();
      }
    });
  }

  _showUI() {
    if (this._isViewInBalloon) {
      return;
    }

    this._balloon.add({
      view: this.actionsView,
      position: this._getBalloonPositionData()
    });
  }

  _hideUI() {
    if (!this._isViewInBalloon) {
      return;
    }

    const editor = this.editor;

    editor.editing.view.focus();
    this._balloon.remove(this.actionsView);
  }

  _getSelectedOembedWidget() {
    const view = this.editor.editing.view;
    const selection = view.document.selection;
    const selectedElement = selection.getSelectedElement();

    if (selectedElement && isWidget(selectedElement) && !!selectedElement.getCustomProperty('OembedEntity')) {
      return selectedElement;
    }

    return null;
  }

  _getBalloonPositionData() {
    const view = this.editor.editing.view;
    const viewDocument = view.document;
    let target = null;

    // Set a target position by converting view selection range to DOM.
    target = () => view.domConverter.viewRangeToDom(
      viewDocument.selection.getFirstRange()
    );

    return {
      target
    };
  }

  get _isViewInBalloon() {
    return !!this.actionsView && this._balloon.hasView(this.actionsView);
  }

}
