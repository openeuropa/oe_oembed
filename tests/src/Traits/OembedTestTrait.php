<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_oembed\Traits;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\editor\Entity\Editor;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaTypeInterface;
use Drupal\node\Entity\NodeType;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Trait used for the oEmbed related tests for quick environment setup.
 */
trait OembedTestTrait {

  use MediaTypeCreationTrait;

  /**
   * The test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Creates all the media types and example media entities.
   */
  public function createMedia(): void {
    // Create a media type for the 3 core source plugin types we support.
    $this->createMediaType('image', ['id' => 'image']);
    $this->createMediaType('oembed:video', ['id' => 'remote_video']);
    $this->createMediaType('file', ['id' => 'file']);
    $this->createMediaType('test', ['id' => 'test']);

    /** @var \Drupal\media\MediaTypeInterface[] $media_types */
    $media_types = $this->container->get('entity_type.manager')->getStorage('media_type')->loadMultiple();

    // Create a view mode which will not have the source configured.
    EntityViewMode::create([
      'label' => 'Missing source',
      'id' => 'media.missing_source',
      'targetEntityType' => 'media',
    ])->save();

    $this->createImageDisplayModes($media_types['image']);
    $this->createRemoteVideoDisplayModes($media_types['remote_video']);

    $this->ensurePublicFilesPath();

    // Create a media entity for each type.
    $this->container->get('file_system')->copy(\Drupal::service('extension.list.module')->getPath('oe_oembed') . '/tests/fixtures/example_1.jpeg', 'public://example_1.jpeg');
    $image = File::create([
      'uri' => 'public://example_1.jpeg',
    ]);
    $image->save();

    $media_type = $media_types['image'];
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => $this->randomMachineName(),
      $source_field->getName() => [$image],
    ]);
    $media->save();

    $media_type = $media_types['remote_video'];
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => $this->randomMachineName(),
      $source_field->getName() => 'https://www.youtube.com/watch?v=1-g73ty9v04',
    ]);
    $media->save();

    $this->container->get('file_system')->copy(\Drupal::service('extension.list.module')->getPath('oe_oembed') . '/tests/fixtures/sample.pdf', 'public://sample.pdf');
    $file = File::create([
      'uri' => 'public://sample.pdf',
    ]);
    $file->save();

    $media_type = $media_types['file'];
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => $this->randomMachineName(),
      $source_field->getName() => [$file],
    ]);
    $media->save();

    $media_type = $media_types['test'];
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => $this->randomMachineName(),
      $source_field->getName() => 'Test',
    ]);
    $media->save();
  }

  /**
   * Creates the display modes for the Image media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type.
   */
  protected function createImageDisplayModes(MediaTypeInterface $media_type): void {
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);

    $default = EntityViewDisplay::create([
      'status' => TRUE,
      'targetEntityType' => 'media',
      'bundle' => 'image',
      'mode' => 'default',
      'content' => [
        $source_field->getName() => [
          'type' => 'image',
          'weight' => 0,
          'region' => 'content',
          'label' => 'hidden',
          'settings' => [
            'image_style' => '',
            'image_link' => '',
          ],
          'third_party_settings' => [],
        ],
      ],
    ]);
    $default->save();

    // Full view mode which uses an image style.
    $full = $default->createDuplicate();
    $full->set('mode', 'full');
    $content = $full->get('content');
    $content[$source_field->getName()]['settings']['image_style'] = 'thumbnail';
    $full->set('content', $content);
    $full->save();

    $missing = $default->createDuplicate();
    $missing->set('mode', 'missing_source');
    $content = $missing->get('content');
    unset($content[$source_field->getName()]);
    $missing->set('content', $content);
    $missing->save();

    // Responsive view mode which uses the responsive image style.
    ResponsiveImageStyle::create([
      'id' => 'responsive_style',
      'label' => 'Responsive style',
      'breakpoint_group' => 'bartik',
      'fallback_image_style' => 'large',
    ])->save();
    EntityViewMode::create([
      'label' => 'Responsive view mode',
      'id' => 'media.responsive',
      'targetEntityType' => 'media',
    ])->save();

    $responsive = $default->createDuplicate();
    $responsive->set('mode', 'responsive');
    $content = $responsive->get('content');
    $content[$source_field->getName()]['type'] = 'responsive_image';
    unset($content[$source_field->getName()]['settings']['image_style']);
    unset($content[$source_field->getName()]['settings']['image_link']);
    unset($content[$source_field->getName()]['settings']['image_loading']);
    $content[$source_field->getName()]['settings']['responsive_image_style'] = 'responsive_style';
    $responsive->set('content', $content);
    $responsive->save();
  }

  /**
   * Creates the display modes for the remote video media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type.
   */
  protected function createRemoteVideoDisplayModes(MediaTypeInterface $media_type): void {
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);

    $default = EntityViewDisplay::create([
      'status' => TRUE,
      'targetEntityType' => 'media',
      'bundle' => 'remote_video',
      'mode' => 'default',
      'content' => [
        $source_field->getName() => [
          'type' => 'oembed',
          'weight' => 0,
          'region' => 'content',
          'label' => 'hidden',
          'settings' => [
            'max_width' => 400,
            'max_height' => 250,
          ],
          'third_party_settings' => [],
        ],
      ],
    ]);
    $default->save();

    $missing = $default->createDuplicate();
    $missing->set('mode', 'missing_source');
    $content = $missing->get('content');
    unset($content[$source_field->getName()]);
    $missing->set('content', $content);
    $missing->save();
  }

  /**
   * Sets the path to the public file system.
   */
  protected function ensurePublicFilesPath() {
    $path = 'http://example.com/sites/default/files';
    if ($this instanceof KernelTestBase) {
      $this->setSetting('file_public_base_url', $path);
      return;
    }

    $this->publicFilesDirectory = $path;
  }

  /**
   * Performs the basic setup of the test.
   */
  protected function basicSetup(): void {
    node_add_body_field(NodeType::load('page'));

    $format = FilterFormat::create([
      'format' => 'html',
      'name' => 'Html format',
      'filters' => [
        'filter_align' => [
          'status' => 1,
        ],
        'filter_caption' => [
          'status' => 1,
        ],
        'filter_html_image_secure' => [
          'status' => 1,
        ],
      ],
    ]);
    $format->save();

    $editor = Editor::create([
      'format' => 'html',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => [
            'media',
            'node',
          ],
        ],
      ],
    ]);
    $editor->save();

    // Create a user with required permissions.
    // @phpstan-ignore-next-line
    $this->user = $this->drupalCreateUser([
      'access content',
      'create page content',
      'use text format html',
      'create image media',
      'create remote_video media',
    ]);
    // @phpstan-ignore-next-line
    $this->drupalLogin($this->user);
  }

  /**
   * Retrieves an embed dialog based on given parameters.
   *
   * @param string|null $filter_format_id
   *   ID of the filter format.
   * @param string|null $embed_button_id
   *   ID of the embed button.
   *
   * @return string
   *   The retrieved HTML string.
   */
  protected function getEmbedDialog(?string $filter_format_id = NULL, ?string $embed_button_id = NULL): string {
    $url = 'oe-oembed-embed/dialog';
    if (!empty($filter_format_id)) {
      $url .= '/' . $filter_format_id;
      if (!empty($embed_button_id)) {
        $url .= '/' . $embed_button_id;
      }
    }
    // @phpstan-ignore-next-line
    return $this->drupalGet($url);
  }

}
