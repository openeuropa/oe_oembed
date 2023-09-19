<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\Traits;

use Drupal\Component\Utility\NestedArray;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;

/**
 * Contains methods to create media entities for testing.
 *
 * When adding a method to create a specific bundle, the method name MUST follow
 * the naming: "create" + bundle name in camel case + "Media".
 */
trait MediaCreationTrait {

  /**
   * Create a remote video media with default values, ready to use in tests.
   *
   * @param array $values
   *   (optional) An array of values to set, keyed by property name.
   *
   * @return \Drupal\media\MediaInterface
   *   The media entity.
   */
  protected function createRemoteVideoMedia(array $values = []): MediaInterface {
    $values['bundle'] = 'remote_video';
    // Title is fetched automatically from remote, so it must stay empty.
    $values['name'] = NULL;

    return $this->createMedia($values + [
      'oe_media_oembed_video' => 'https://www.youtube.com/watch?v=1-g73ty9v04',
    ]);
  }

  /**
   * Create an image media with default values, ready to use in tests.
   *
   * @param array $values
   *   (optional) An array of values to set, keyed by property name.
   *
   * @return \Drupal\media\MediaInterface
   *   The media entity.
   */
  protected function createImageMedia(array $values = []): MediaInterface {
    $values['bundle'] = 'image';

    if (!isset($values['oe_media_image']['target_id'])) {
      $image = File::create([
        'uri' => \Drupal::service('file_system')->copy(
          \Drupal::service('extension.list.theme')->getPath('oe_whitelabel') . '/tests/fixtures/example_1.jpeg',
          'public://example_1.jpeg'
        ),
      ]);
      $image->save();

      $values = NestedArray::mergeDeep([
        'oe_media_image' => [
          'target_id' => $image->id(),
          'alt' => 'Alt text',
        ],
      ], $values);
    }

    return $this->createMedia($values + [
      'name' => 'Image title',
    ]);
  }

  /**
   * Creates a media entity.
   *
   * @param array $values
   *   (optional) An array of values to set, keyed by property name.
   *
   * @return \Drupal\media\MediaInterface
   *   The created entity.
   */
  protected function createMedia(array $values = []): MediaInterface {
    /** @var \Drupal\media\MediaInterface $entity */
    $entity = \Drupal::entityTypeManager()
      ->getStorage('media')
      ->create($values);
    $entity->save();

    return $entity;
  }

}
