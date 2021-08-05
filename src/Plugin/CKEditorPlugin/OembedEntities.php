<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Plugin\CKEditorPlugin;

use Drupal\editor\Entity\Editor;
use Drupal\embed\EmbedCKEditorPluginBase;

/**
 * Defines the plugin responsible for embedding Drupal entities.
 *
 * @CKEditorPlugin(
 *   id = "oe_oembed_entities",
 *   label = @Translation("OEmbed Drupal entities"),
 *   embed_type_id = "oe_oembed_entities"
 * )
 */
class OembedEntities extends EmbedCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile(): string {
    return drupal_get_path('module', 'oe_oembed') . '/js/plugins/oe_oembed_entities/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor): array {
    return [
      'Oembed_buttons' => $this->getButtons(),
    ];
  }

}
