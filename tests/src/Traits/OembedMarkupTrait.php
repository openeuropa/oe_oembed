<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\Traits;

/**
 * Contains methods to generate the markup as created by the plugin itself.
 */
trait OembedMarkupTrait {

  /**
   * Returns the markup for an embedded entity with a non-inline view mode.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $view_mode
   *   The view mode.
   * @param string $uuid
   *   The entity UUID.
   * @param string $label
   *   The entity label.
   *
   * @return string
   *   The embed markup as created by the JS plugin.
   */
  protected function getBlockEmbedMarkup(string $entity_type, string $view_mode, string $uuid, string $label): string {
    return sprintf(
      '<p data-display-as="%s" data-oembed="https://oembed.ec.europa.eu?url=https%%3A//data.ec.europa.eu/ewp/%s/%s%%3Fview_mode%%3D%s"><a href="https://data.ec.europa.eu/ewp/%s/%s">%s</a></p>',
      $view_mode,
      $entity_type,
      $uuid,
      $view_mode,
      $entity_type,
      $uuid,
      $label
    );
  }

  /**
   * Returns the markup for an embedded entity with an inline view mode.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $view_mode
   *   The view mode.
   * @param string $uuid
   *   The entity UUID.
   * @param string $label
   *   The entity label.
   *
   * @return string
   *   The embed markup as created by the JS plugin.
   */
  protected function getInlineEmbedMarkup(string $entity_type, string $view_mode, string $uuid, string $label): string {
    return sprintf(
      '<a href="https://data.ec.europa.eu/ewp/%s/%s" data-display-as="%s" data-oembed="https://oembed.ec.europa.eu?url=https%%3A//data.ec.europa.eu/ewp/%s/%s%%3Fview_mode%%3D%s">%s</a>',
      $entity_type,
      $uuid,
      $view_mode,
      $entity_type,
      $uuid,
      $view_mode,
      $label
    );
  }

}
