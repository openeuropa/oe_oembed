import { Plugin } from 'ckeditor5/src/core';
import { Widget, toWidget } from 'ckeditor5/src/widget';
import OembedEntitiesCommand from "./oembedentitiescommand";

// cSpell:ignore simplebox insertsimpleboxcommand

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * CKEditor 5 internally interacts with simpleBox as this model:
 * <simpleBox>
 *    <simpleBoxTitle></simpleBoxTitle>
 *    <simpleBoxDescription></simpleBoxDescription>
 * </simpleBox>
 *
 * Which is converted for the browser/user as this markup
 * <section class="simple-box">
 *   <h2 class="simple-box-title"></h1>
 *   <div class="simple-box-description"></div>
 * </section>
 *
 * This file has the logic for defining the simpleBox model, and for how it is
 * converted to standard DOM markup.
 */
export default class OembedEntitiesEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  static get pluginName() {
    return 'OembedEntitiesEditing';
  }

  constructor(editor) {
    super(editor);

    this.viewAttrs = {
      oembedEntitiesOembed: 'data-oembed',
      oembedEntitiesDisplayAs: 'data-display-as',
      oembedEntitiesEmbedInline: 'data-embed-inline',
    };
    this.modelAttrs = Object.assign({
      oembedEntitiesResourceLabel: 'data-resource-label',
      oembedEntitiesResourceUrl: 'data-resource-url',
      oembedEntitiesButtonId: 'data-button-id',
    }, this.viewAttrs);
  }

  init() {
    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'oembedEntities',
      new OembedEntitiesCommand(this.editor),
    );
  }

  _defineSchema() {
    // Schemas are registered via the central `editor` object.
    const schema = this.editor.model.schema;

    schema.register('oembedEntity', {
      // Behaves like a self-contained object (e.g. an image).
      isObject: true,
      isBlock: true,
      // Allow in places where other blocks are allowed (e.g. directly in the root).
      allowWhere: '$block',
      allowAttributes: Object.keys(this.modelAttrs)
    });

    schema.register('oembedEntityInline', {
      inheritAllFrom: '$inlineObject',
      allowAttributes: Object.keys(this.modelAttrs)
    });
  }

  /**
   * Converters determine how CKEditor 5 models are converted into markup and
   * vice-versa.
   */
  _defineConverters() {
    // Converters are registered via the central editor object.
    const { conversion } = this.editor;

    conversion
      .for('upcast')
      .add(dispatcher => {
        dispatcher.on('element:p', (evt, data, conversionApi) => {
          const {
            consumable,
            writer,
            safeInsert,
            updateConversionResult
          } = conversionApi;

          // Get view item from data object.
          const { viewItem } = data;

          // Define elements consumables.
          // This basically is used to check if the element or its attributes
          // have been already "consumed" (converted) by another converter.
          const wrapper = { name: true };
          const link = { name: true, attributes: 'href' };

          // We are converting only p[data-oembed] elements.
          // We do not use this attribute in the wrapper consumable above
          // so that the attributes downcast will do it for us.
          if (!viewItem.hasAttribute('data-oembed')) {
            return;
          }

          // Tests if the view element can be consumed.
          if (!consumable.test(viewItem, wrapper)) {
            return;
          }

          // Check if there is only one child.
          if (viewItem.childCount !== 1) {
            return;
          }

          // Get the first child element.
          const linkItem = viewItem.getChild(0);

          // Check if the first element is a link.
          if (!linkItem.is('element', 'a')) {
            return;
          }

          // Tests if the first child element can be consumed.
          if (!consumable.test(linkItem, link)) {
            return;
          }

          // The link should have one text node only.
          if (linkItem.childCount !== 1) {
            return;
          }

          const textNode = linkItem.getChild(0);
          if (!textNode.is('text')) {
            return;
          }

          if (!consumable.test(textNode)) {
            return;
          }

          // Create our model with the two attributes coming from the link
          // element. The rest of the attributes will be converted by a
          // dedicated upcast.
          const modelElement = writer.createElement('oembedEntity', {
            oembedEntitiesResourceUrl: linkItem.getAttribute('href'),
            oembedEntitiesResourceLabel: textNode.data,
          });

          // Insert element on a current cursor location.
          if (!safeInsert(modelElement, data.modelCursor)) {
            return;
          }

          // Mark objects as consumed.
          consumable.consume(viewItem, wrapper);
          consumable.consume(linkItem, link);
          consumable.consume(textNode);

          // Necessary function call to help setting model range and cursor
          // for some specific cases when elements being split.
          updateConversionResult(modelElement, data);
          // This converter is marked with high priority, so it runs before
          // the default <p> conversion.
        }, { priority: 'high' });
      });

    conversion
      .for('dataDowncast')
      .elementToStructure({
        model: 'oembedEntity',
        view: (modelElement, { writer }) => {
          return this._generateViewBlockElement(modelElement, writer, true);
        },
      });

    conversion
      .for('editingDowncast')
      .elementToStructure({
        model: 'oembedEntity',
        view: (modelElement, { writer }) => {
          const container = this._generateViewBlockElement(modelElement, writer);

          writer.setAttribute('data-oembed', '', container);
          writer.setCustomProperty('OembedEntity', true, container);

          return toWidget(container, writer, {
            label: Drupal.t('OpenEuropa Oembed widget'),
          })
        },
      });

    // Set attributeToAttribute conversion for all supported attributes.
    Object.keys(this.viewAttrs).forEach((modelKey) => {
      const attributeMapping = {
        model: {
          key: modelKey,
          name: 'oembedEntity',
        },
        view: {
          name: 'p',
          key: this.viewAttrs[modelKey],
        },
      };
      // Attributes should be rendered only in dataDowncast to avoid having
      // unfiltered data-attributes.
      conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
      conversion.for('upcast').attributeToAttribute(attributeMapping);
    });

    conversion
      .for('dataDowncast')
      .elementToElement({
        model: 'oembedEntityInline',
        view: (modelElement, { writer }) => {
          return this._generateViewInlineElement(modelElement, writer, true);
        },
      });

    conversion
      .for('editingDowncast')
      .elementToElement({
        model: 'oembedEntityInline',
        view: (modelElement, { writer }) => {
          const container = this._generateViewInlineElement(modelElement, writer);

          writer.setAttribute('data-oembed', '', container.getChild(0));
          writer.setCustomProperty('OembedEntity', true, container);

          return toWidget(container, writer, {
            label: Drupal.t('OpenEuropa Oembed widget'),
          })
        },
      });

    // We need to use a structure conversion as we don't only need to process the "a" tag,
    // but also consume the inner text.
    conversion
      .for('upcast')
      .add(dispatcher => {
        dispatcher.on('element:a', (evt, data, conversionApi) => {
          const {
            consumable,
            writer,
            safeInsert,
            updateConversionResult
          } = conversionApi;

          // Get view item from data object.
          const { viewItem } = data;
          const link = { name: true, attributes: 'href' };

          // We are converting only a[data-oembed] elements.
          // We do not use this attribute in the wrapper consumable above
          // so that the attributes downcast will do it for us.
          if (!viewItem.hasAttribute('data-oembed')) {
            return;
          }

          // Tests if the view element can be consumed.
          if (!consumable.test(viewItem, link)) {
            return;
          }

          // Check if there is only one child.
          if (viewItem.childCount !== 1) {
            return;
          }

          const textNode = viewItem.getChild(0);
          if (!textNode.is('text')) {
            return;
          }

          if (!consumable.test(textNode)) {
            return;
          }

          // Create our model with the two attributes coming from the link
          // element. The rest of the attributes will be converted by a
          // dedicated upcast.
          const modelElement = writer.createElement('oembedEntityInline', {
            oembedEntitiesResourceUrl: viewItem.getAttribute('href'),
            oembedEntitiesResourceLabel: textNode.data,
          });

          // Insert element on a current cursor location.
          if (!safeInsert(modelElement, data.modelCursor)) {
            return;
          }

          // Mark objects as consumed.
          consumable.consume(viewItem, link);
          consumable.consume(textNode);

          // Necessary function call to help setting model range and cursor
          // for some specific cases when elements being split.
          updateConversionResult(modelElement, data);
          // This converter is marked with high priority, so it runs before
          // the default <a> conversion.
        }, { priority: 'high' });
      });

    Object.keys(this.viewAttrs).forEach((modelKey) => {
      const attributeMapping = {
        model: {
          key: modelKey,
          name: 'oembedEntityInline',
        },
        view: {
          name: 'a',
          key: this.viewAttrs[modelKey],
        },
      };
      // Attributes should be rendered only in dataDowncast to avoid having
      // unfiltered data-attributes.
      conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
      conversion.for('upcast').attributeToAttribute(attributeMapping);
    });
  }

  _generateViewInlineElement(modelElement, writer, forDataDowncast = false) {
    const link = writer.createContainerElement('a', {
      href: modelElement.getAttribute('oembedEntitiesResourceUrl')
    }, { priority: 5 });

    const text = writer.createText(modelElement.getAttribute('oembedEntitiesResourceLabel'));
    writer.insert(writer.createPositionAt(link, 0), text);

    return forDataDowncast ? link : writer.createContainerElement('span', {}, [link]);
  }

  _generateViewBlockElement(modelElement, writer, forDataDowncast = false) {
    const link = this._generateViewInlineElement(modelElement, writer, forDataDowncast);

    return writer.createContainerElement('p', {}, [link]);
  }

}
