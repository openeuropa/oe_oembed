/* eslint-disable import/no-extraneous-dependencies */

// cspell:ignore focusables

import {
  ButtonView,
  FocusCycler,
  LabeledFieldView,
  View,
  ViewCollection,
  createLabeledInputText,
  injectCssTransitionDisabler,
  submitHandler,
  Template,
} from 'ckeditor5/src/ui';
import { FocusTracker, KeystrokeHandler } from 'ckeditor5/src/utils';
import { icons } from 'ckeditor5/src/core';

// cspell:ignore focusables

export default class OembedEntitiesActionsView extends View {

  /**
   * @inheritDoc
   */
  constructor(locale) {
    super(locale);

    this.keystrokes = new KeystrokeHandler();
    this.editButtonView = this._createButton(Drupal.t('Edit embedded entity'), icons.pencil, 'edit');

    this.setTemplate({
      tag: 'div',
      attributes: {
        class: [
          'ck',
          'ck-link-actions',
          'ck-responsive-form'
        ],
        // https://github.com/ckeditor/ckeditor5-link/issues/90
        tabindex: '-1'
      },
      children: [
        this.editButtonView,
      ]
    });
  }

  /**
   * @inheritDoc
   */
  destroy() {
    super.destroy();
    this.keystrokes.destroy();
  }

  _createButton(label, icon, eventName) {
    const button = new ButtonView(this.locale);

    button.set({
      label,
      icon,
      tooltip: true
    });
    button.delegate('execute').to(this, eventName);

    return button;
  }

}
