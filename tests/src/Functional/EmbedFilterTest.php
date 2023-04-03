<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\editor\Entity\Editor;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\media\Entity\Media;

/**
 * Tests the oEmbed filter.
 */
class EmbedFilterTest extends EmbedTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $format = FilterFormat::create([
      'format' => 'format_with_embed',
      'name' => 'Format with embed',
      'filters' => [
        'oe_oembed_filter' => [
          'status' => 1,
        ],
      ],
    ]);
    $format->save();

    $editor_group = [
      'name' => 'Entity Embed',
      'items' => [
        'media',
        'node',
      ],
    ];

    $editor = Editor::create([
      'format' => 'format_with_embed',
      'editor' => 'ckeditor',
      'settings' => [
        'toolbar' => [
          'rows' => [[$editor_group]],
        ],
      ],
    ]);
    $editor->save();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->createTestEntities();

    $view_display = EntityViewDisplay::load('node.page.inline');
    $view_display->setThirdPartySetting('oe_oembed', 'inline', TRUE);
    $view_display->setThirdPartySetting('oe_oembed', 'embeddable', TRUE);
    $view_display->save();
  }

  /**
   * Tests that the oEmbed filter correctly renders the oEmbed tags.
   */
  public function testEmbedFilter(): void {
    $assert_session = $this->assertSession();

    $media_data = [];

    // Log out so we visit all pages as anonymous.
    $this->drupalLogout();

    // Remote video media.
    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'remote_video']);
    $media = reset($media);
    $content = '<p data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/' . $media->uuid() . '"><a href="https://data.ec.europa.eu/ewp/media/' . $media->uuid() . '">Digital Single Market: cheaper calls to other EU countries as of 15 May</a></p>';
    $values = [];
    $values['type'] = 'page';
    $values['title'] = 'Test video embed node';
    $values['body'] = [['value' => $content, 'format' => 'format_with_embed']];
    $node = $this->drupalCreateNode($values);
    $this->drupalGet('node/' . $node->id());
    $assert_session->elementExists('css', '.field--name-oe-media-oembed-video');
    $assert_session->elementExists('css', 'iframe.media-oembed-content');
    $assert_session->elementAttributeContains('css', 'iframe.media-oembed-content', 'src', 'media/oembed?url=https%3A//www.youtube.com/watch%3Fv%3DOkPW9mK5Vw8');
    $assert_session->responseNotContains($content);
    $media_data[$media->uuid()] = $media->label();

    // Image media.
    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'image']);
    $media = reset($media);
    $content = '<p data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/' . $media->uuid() . '"><a href="https://data.ec.europa.eu/ewp/media/' . $media->uuid() . '">Test image media</a></p>';
    $values = [];
    $values['type'] = 'page';
    $values['title'] = 'Test image embed node';
    $values['body'] = [['value' => $content, 'format' => 'format_with_embed']];
    $node = $this->drupalCreateNode($values);
    $this->drupalGet('node/' . $node->id());
    // Check that the media element got rendered.
    $assert_session->elementAttributeContains('css', 'img', 'src', 'files/example_1.jpeg');
    $media_data[$media->uuid()] = $media->label();

    // Document media.
    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'document']);
    $media = reset($media);
    $content = '<p data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/' . $media->uuid() . '"><a href="https://data.ec.europa.eu/ewp/media/' . $media->uuid() . '">Test document media</a></p>';
    $values = [];
    $values['type'] = 'page';
    $values['title'] = 'Test document embed node';
    $values['body'] = [['value' => $content, 'format' => 'format_with_embed']];
    $node = $this->drupalCreateNode($values);
    $this->drupalGet('node/' . $node->id());
    // Check that the media element got rendered.
    $assert_session->elementExists('css', '.media--type-document');
    $assert_session->linkExists('sample.pdf');
    $assert_session->elementAttributeContains('css', '.field--name-oe-media-file a', 'href', 'sample.pdf');
    $assert_session->responseNotContains($content);
    $media_data[$media->uuid()] = $media->label();

    // Create a node with all the media and node embeds.
    $content = '';
    foreach ($media_data as $uuid => $name) {
      $content .= '<p data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/' . $uuid . '"><a href="https://data.ec.europa.eu/ewp/media/' . $uuid . '">' . $name . '</a></p>';
    }
    $embedded_node = $this->drupalGetNodeByTitle('Embedded node');
    $content .= '<p data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/node/' . $embedded_node->uuid() . '"><a href="https://data.ec.europa.eu/ewp/node/' . $embedded_node->uuid() . '">' . $embedded_node->label() . '</a></p>';
    $content .= '<a data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/node/' . $embedded_node->uuid() . '%3Fview_mode%3Dinline" href="https://data.ec.europa.eu/ewp/node/' . $embedded_node->uuid() . '">' . $embedded_node->label() . '</a>';

    $values = [];
    $values['type'] = 'page';
    $values['title'] = 'Test node with all the embeds';
    $values['body'] = [['value' => $content, 'format' => 'format_with_embed']];
    $node = $this->drupalCreateNode($values);
    $this->drupalGet('node/' . $node->id());
    // Check that the media elements got rendered.
    $assert_session->elementExists('css', '.field--name-oe-media-oembed-video');
    $assert_session->elementAttributeContains('css', '.field--name-oe-media-image img', 'src', 'files/example_1.jpeg');
    $assert_session->elementExists('css', '.media--type-document');
    $assert_session->responseNotContains($content);
    // Check that the node elements got rendered (both block and inline).
    $assert_session->pageTextContains('Embedded node');
    $assert_session->elementExists('css', '.node--type-page.node--promoted.node--view-mode-default');
    $assert_session->elementExists('css', '.node--type-page.node--promoted.node--view-mode-inline');
    $assert_session->responseNotContains('<p data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/node/' . $embedded_node->uuid() . '"><a href="https://data.ec.europa.eu/ewp/node/' . $embedded_node->uuid() . '">' . $embedded_node->label() . '</a></p>');
    $assert_session->responseNotContains('<a data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/node/' . $embedded_node->uuid() . '%3Fview_mode%3Dinline" href="https://data.ec.europa.eu/ewp/node/' . $embedded_node->uuid() . '">' . $embedded_node->label() . '</a>');

    // Unpublish the node entity and ensure it's no longer in the markup.
    $embedded_node->setUnpublished();
    $embedded_node->save();
    $this->drupalGet('node/' . $node->id());
    $assert_session->elementExists('css', '.field--name-oe-media-oembed-video');
    $assert_session->elementExists('css', '.field--name-oe-media-image img');
    $assert_session->elementExists('css', '.media--type-document');
    $assert_session->pageTextNotContains('Embedded node');

    // Delete all the media entities and ensure they are no longer
    // shown in the markup.
    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['uuid' => array_keys($media_data)]);
    foreach ($media as $entity) {
      $entity->delete();
    }
    $embedded_node->delete();

    $this->drupalGet('node/' . $node->id());
    $assert_session->elementNotExists('css', '.field--name-oe-media-oembed-video');
    $assert_session->elementNotExists('css', '.field--name-oe-media-image img');
    $assert_session->elementNotExists('css', '.media--type-document');
    $assert_session->pageTextNotContains('Embedded node');

    // Test a non-valid embed that doesn't get replaced.
    $content = '<p data-oembed="https://oembed.ec.europa.eu">Test no real media embed</p>';
    $values = [];
    $values['type'] = 'page';
    $values['title'] = 'Test no real media embed';
    $values['body'] = [['value' => $content, 'format' => 'format_with_embed']];
    $node = $this->drupalCreateNode($values);
    $this->drupalGet('node/' . $node->id());
    $assert_session->responseContains($content);

    // Test a non-valid embed URL that gets removed.
    $content = '<p data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/14d7e768-1b50-11ec-b4fe-01aa75ed71a1"><a href="https://data.ec.europa.eu/ewp/14d7e768-1b50-11ec-b4fe-01aa75ed71a1">Has no entity type</a></p>';
    $values = [];
    $values['type'] = 'page';
    $values['title'] = 'Test no valid URL';
    $values['body'] = [['value' => $content, 'format' => 'format_with_embed']];
    $node = $this->drupalCreateNode($values);
    $this->drupalGet('node/' . $node->id());
    $assert_session->pageTextContains('Test no valid URL');
    $assert_session->responseNotContains('oembed.ec.europa.eu');
    $assert_session->responseNotContains('Has no entity type');
  }

  /**
   * Creates 3 media entities, one of each type and a Page node.
   */
  protected function createTestEntities(): void {
    /** @var \Drupal\media\MediaTypeInterface[] $media_types */
    $media_types = $this->container->get('entity_type.manager')->getStorage('media_type')->loadMultiple();

    // Image media.
    $oe_media_module_path = \Drupal::service('extension.list.module')->getPath('oe_media');
    $this->container->get('file_system')->copy($oe_media_module_path . '/tests/fixtures/example_1.jpeg', 'public://example_1.jpeg');
    $image = File::create([
      'uri' => 'public://example_1.jpeg',
    ]);
    $image->save();

    $media_type = $media_types['image'];
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Test image media',
      $source_field->getName() => [$image],
    ]);

    $media->save();

    // Remote video media.
    $media_type = $media_types['remote_video'];
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $media = Media::create([
      'bundle' => $media_type->id(),
      $source_field->getName() => 'https://www.youtube.com/watch?v=OkPW9mK5Vw8',
    ]);

    $media->save();

    // File media.
    $this->container->get('file_system')->copy($oe_media_module_path . '/tests/fixtures/sample.pdf', 'public://sample.pdf');
    $file = File::create([
      'uri' => 'public://sample.pdf',
    ]);
    $file->save();

    $media_type = $media_types['document'];
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Test document media',
      $source_field->getName() => [$file],
    ]);

    $media->save();

    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Embedded node',
    ]);
  }

}
