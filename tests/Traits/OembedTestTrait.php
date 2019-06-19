<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\Traits;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaTypeInterface;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Trait used for the oEmbed related tests for quick environment setup.
 */
trait OembedTestTrait {

  use MediaTypeCreationTrait;

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
    $this->container->get('file_system')->copy(drupal_get_path('module', 'oe_oembed') . '/tests/fixtures/example_1.jpeg', 'public://example_1.jpeg');
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

    $this->container->get('file_system')->copy(drupal_get_path('module', 'oe_oembed') . '/tests/fixtures/sample.pdf', 'public://sample.pdf');
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

}
