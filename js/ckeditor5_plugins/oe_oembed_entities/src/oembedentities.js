import OembedEntitiesEditing from './oembedentitiesediting';
import OembedEntitiesUI from "./oembedentitiesui";
import { Plugin } from 'ckeditor5/src/core';
import OembedToolbar from "./oembedtoolbar";

export default class OembedEntities extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [OembedEntitiesEditing, OembedEntitiesUI, OembedToolbar];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'OembedEntities';
  }
}
