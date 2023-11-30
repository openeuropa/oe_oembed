import OembedEntitiesEditing from './oembedentitiesediting';
import OembedEntitiesUI from "./oembedentitiesui";
import { Plugin } from 'ckeditor5/src/core';
import OembedToolbar from "./oembedtoolbar";

/**
 * Main entrypoint for the OembedEntities plugin.
 */
export default class OembedEntities extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [OembedEntitiesEditing, OembedEntitiesUI, OembedToolbar];
  }

}
