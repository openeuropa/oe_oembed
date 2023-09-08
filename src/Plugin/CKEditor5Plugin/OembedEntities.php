<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo use EmbedCKEditor5PluginBase when pr is ready for embed module.
 */
class OembedEntities extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new instance of this class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    // @todo this method is called for each button present in the editor!
    $dynamic_plugin_config = $static_plugin_config;

    // Register embed buttons as individual buttons on admin pages.
    $embed_buttons = $this
      ->entityTypeManager
      ->getStorage('embed_button')
      ->loadByProperties([
        'type_id' => 'oe_oembed_entities',
      ]);

    $toolbar_items = $editor->getSettings()['toolbar']['items'];
    $buttons = [];
    $default_buttons = [];

    /** @var \Drupal\embed\EmbedButtonInterface $embed_button */
    foreach ($embed_buttons as $embed_button) {
      $id = $embed_button->id();

      // This is needed only because we are loading all the buttons instead
      // of only the current plugin button.
      if (!in_array($id, $toolbar_items)) {
        continue;
      }

      $label = Html::escape($embed_button->label());
      $buttons[$id] = [
        'name' => $label,
        'label' => $label,
        'icon' => $embed_button->getIconUrl(),
      ];

      $entity_type = $embed_button->getTypeSetting('entity_type');
      if (!isset($default_buttons[$entity_type])) {
        $default_buttons[$entity_type] = $id;
      }
    }

    // Add configured embed buttons and pass it to the UI.
    $dynamic_plugin_config['oembedEntities'] = [
      'buttons' => $buttons,
      'defaultButtons' => $default_buttons,
      'format' => $editor->getFilterFormat()->id(),
      'dialogSettings' => [
        'dialogClass' => 'oe-oembed-entities-select-dialog',
        'resizable' => FALSE,
      ],
    ];

    return $dynamic_plugin_config;
  }

}
