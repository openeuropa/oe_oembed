/**
 * @file defines InsertSimpleBoxCommand, which is executed when the simpleBox
 * toolbar button is pressed.
 */
// cSpell:ignore simpleboxediting

import { Command } from 'ckeditor5/src/core';

export default class OembedEntitiesCommand extends Command {

  /**
   * @inheritdoc
   */
  execute(attributes) {
    const { model } = this.editor;
    const oembedEntitiesEditing = this.editor.plugins.get('OembedEntitiesEditing');

    // Create object that contains supported data-attributes in view data by
    // flipping `EntityEmbedEditing.modelAttrs` object (i.e. keys from object become
    // values and values from object become keys).
    const dataAttributeMapping = Object.entries(oembedEntitiesEditing.modelAttrs).reduce(
      (result, [key, value]) => {
        result[value] = key;
        return result;
      },
      {},
    );

    // \Drupal\entity_embed\Form\EntityEmbedDialog returns data in keyed by
    // data-attributes used in view data. This converts data-attribute keys to
    // keys used in model.
    const modelAttributes = Object.keys(attributes).reduce(
      (result, attribute) => {
        if (dataAttributeMapping[attribute]) {
          result[dataAttributeMapping[attribute]] = attributes[attribute];
        }
        return result;
      },
      {},
    );

    // @todo Check if we prefer this.
    //const modelAttributes = {}
    //for (const [modelAttribute, dataAttribute] of Object.entries(oembedEntitiesEditing.modelAttrs)) {
    //  if (attributes[dataAttribute]) {
    //    modelAttributes[modelAttribute] = attributes[dataAttribute];
    //  }
    //}

    model.change((writer) => {
      model.insertContent(insertOembedEntity(writer, modelAttributes));
    });
  }

  /**
   * @inheritdoc
   */
  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    // Determine if the cursor (selection) is in a position where adding
    // our models is permitted. This is based on the schema of the model(s)
    // currently containing the cursor.
    // This is technically not correct. The models have different allowed
    // parents, but since they share the same button it's not really
    // possible to discern which embed type will be used.
    // We loop and stop as soon as one is allowed.
    this.isEnabled = ['oembedEntity', 'oembedEntityInline'].some(type => {
      const allowedIn = model.schema.findAllowedParent(
        selection.getFirstPosition(),
        type
      );
      return allowedIn !== null;
    });
  }

}

function insertOembedEntity(writer, attributes) {
  const { oembedEntitiesEmbedInline } = attributes;
  const isInline = Boolean(oembedEntitiesEmbedInline);

  return writer.createElement(isInline ? 'oembedEntityInline': 'oembedEntity', attributes);
}
