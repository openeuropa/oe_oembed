<?php

namespace Drupal\oe_oembed\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OembedEntitiesPluginDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getDerivativeDefinitions($base_plugin_definition) {
    $storage = $this->entityTypeManager->getStorage('embed_button');

    foreach ($storage->loadMultiple() as $embed_button) {
      $embed_button_id = $embed_button->id();
      $embed_button_label = Html::escape($embed_button->label());
      $plugin_id = "oe_oembed_entities_{$embed_button_id}";
      $definition = $base_plugin_definition->toArray();
      $definition['id'] = $plugin_id;
      $definition['drupal']['label'] = $this->t('Entity Embed - @label', ['@label' => $embed_button_label])->render();
      $definition['drupal']['toolbar_items'] = [
        $embed_button_id => [
          'label' => $embed_button_label,
        ],
      ];
      $definition['drupal']['elements'][] = '<p data-oembed data-display-as data-embed-inline>';
      $this->derivatives[$plugin_id] = new CKEditor5PluginDefinition($definition);
    }

    return $this->derivatives;
  }


}
