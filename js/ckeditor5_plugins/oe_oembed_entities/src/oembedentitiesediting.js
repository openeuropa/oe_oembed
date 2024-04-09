import { Plugin } from 'ckeditor5/src/core';
import { Widget, toWidget } from 'ckeditor5/src/widget';
import OembedEntitiesCommand from "./oembedentitiescommand";

/**
 * Handles the transformation from the CKEditor 5 UI to Drupal-specific markup.
 *
 * @private
 */
export default class OembedEntitiesEditing extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'OembedEntitiesEditing';
  }

  /**
   * @inheritdoc
   */
  init() {
    this.viewAttrs = {
      oembedEntitiesDisplayAs: 'data-display-as',
      oembedEntitiesOembed: 'data-oembed',
    };
    this.modelAttrs = {
      ...this.viewAttrs,
      oembedEntitiesEmbedInline: 'data-embed-inline',
      oembedEntitiesResourceLabel: 'data-resource-label',
      oembedEntitiesResourceUrl: 'data-resource-url',
      oembedEntitiesButtonId: 'data-button-id',
    };

    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add(
      'oembedEntities',
      new OembedEntitiesCommand(this.editor),
    );
  }

  /**
   * Registers the models schemas.
   *
   * @private
   */
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

    // The inline model behaves like an $inlineObject, but without allowing
    // $text attributes.
    // @see https://ckeditor.com/docs/ckeditor5/latest/framework/deep-dive/schema.html#generic-items
    schema.register('oembedEntityInline', {
      isInline: true,
      isObject: true,
      allowWhere: '$text',
      allowAttributes: Object.keys(this.modelAttrs)
    });
  }

  /**
   * Defines handling of oembed models to and from markup.
   *
   * @private
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

          writer.addClass('ck-oe-oembed', container);
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

          writer.addClass('ck-oe-oembed', container);
          writer.setCustomProperty('OembedEntity', true, container);

          return toWidget(container, writer, {
            label: Drupal.t('OpenEuropa Oembed widget'),
          })
        },
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

  /**
   * Generates an inline view element.
   *
   * @param {module:engine/model/element~Element} modelElement
   *   The oembedEntityInline model element to be converted.
   * @param {module:engine/view/downcastwriter~DowncastWriter} writer
   *   The downcast writer.
   * @param {bool} forDataDowncast
   *   If the conversion is for dataDowncast phase or editingDowncast.
   * @returns {module:engine/view/element~Element}
   *   The view element.
   *
   * @private
   */
  _generateViewInlineElement(modelElement, writer, forDataDowncast = false) {
    const link = writer.createContainerElement('a', {
      href: modelElement.getAttribute('oembedEntitiesResourceUrl')
    });

    const text = writer.createText(modelElement.getAttribute('oembedEntitiesResourceLabel'));
    writer.insert(writer.createPositionAt(link, 0), text);

    return forDataDowncast ? link : writer.createContainerElement('span', {
      'data-display-as': modelElement.getAttribute('oembedEntitiesDisplayAs')
    }, [link]);
  }

  /**
   * Generates a block view element.
   *
   * @param {module:engine/model/element~Element} modelElement
   *   The oembedEntity model element to be converted.
   * @param {module:engine/view/downcastwriter~DowncastWriter} writer
   *   The downcast writer.
   * @param {bool} forDataDowncast
   *   If the conversion is for dataDowncast phase or editingDowncast.
   * @returns {module:engine/view/element~Element}
   *   The view element.
   *
   * @private
   */
  _generateViewBlockElement(modelElement, writer, forDataDowncast = false) {
    const link = this._generateViewInlineElement(modelElement, writer, forDataDowncast);

    return writer.createContainerElement('p', {}, [link]);
  }

}
