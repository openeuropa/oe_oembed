/**
 * @file registers the simpleBox toolbar button and binds functionality to it.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import defaultIcon from '../../../../icons/embed.svg';
import { openDialog } from "./utils";

export default class OembedEntitiesUI extends Plugin {

  init() {
    const editor = this.editor;
    const command = editor.commands.get('oembedEntities');
    const options = editor.config.get('oembedEntities');
    const { dialogSettings = {}, currentRoute, currentRouteParameters } = options;

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
          openDialog(
            libraryUrl,
            {
              'current_route': currentRoute,
              'current_route_parameters': currentRouteParameters,
            },
            ({ attributes }) => {
              // Our module doesn't support sending the button from the response.
              attributes['data-button-id'] = id;
              editor.execute('oembedEntities', attributes);
            },
            dialogSettings,
          )
        );

        return buttonView;
      });
    });
  }

}
