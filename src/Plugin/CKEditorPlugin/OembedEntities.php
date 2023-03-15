<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Plugin\CKEditorPlugin;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\editor\Entity\Editor;
use Drupal\embed\EmbedCKEditorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Constructs a new instance of the class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\Query\QueryInterface $embed_button_query
   *   The entity query object for embed button.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList|NULL $moduleExtensionList
   *   The module extension list.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    QueryInterface $embed_button_query,
    RouteMatchInterface $current_route_match,
    EntityTypeManagerInterface $entityTypeManager,
    ModuleExtensionList $moduleExtensionList = NULL
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $embed_button_query);
    $this->currentRouteMatch = $current_route_match;
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleExtensionList = $moduleExtensionList ?? \Drupal::service('extension.list.module');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('embed_button')->getQuery(),
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFile(): string {
    return $this->moduleExtensionList->getPath('oe_oembed') . '/js/plugins/oe_oembed_entities/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor): array {
    return [
      'current_route' => $this->currentRouteMatch->getRouteName(),
      'current_route_parameters' => $this->currentRouteMatch->getRawParameters()->all(),
      'Oembed_buttons' => $this->getButtons(),
      'Oembed_default_buttons' => $this->getDefaultButtons($editor),
    ];
  }

  /**
   * Returns the default oembed buttons for the editor.
   *
   * For each entity type, we determine a button to be used as default so that
   * we can edit existing embeds where we do not have information about the
   * original button used to embed them.
   *
   * This also takes into account if the button is in fact enabled on the
   * editor.
   *
   * @param \Drupal\editor\Entity\Editor $editor
   *   The editor.
   *
   * @return array
   *   The default buttons.
   */
  protected function getDefaultButtons(Editor $editor): array {
    $buttons = $this->getButtons();
    $default_buttons = [];
    foreach ($buttons as $id => $info) {
      /** @var \Drupal\embed\EmbedButtonInterface $button */
      $button = $this->entityTypeManager->getStorage('embed_button')->load($id);
      $entity_type = $button->getTypeSetting('entity_type');
      if ($this->isButtonEnabled($id, $editor) && !isset($default_buttons[$entity_type])) {
        $default_buttons[$entity_type] = $id;
      }
    }

    return $default_buttons;
  }

  /**
   * Checks if a given button is enabled on the editor.
   *
   * @param string $button
   *   The button ID.
   * @param \Drupal\editor\Entity\Editor $editor
   *   The editor.
   *
   * @return bool
   *   Whether the button is enabled in the editor.
   */
  protected function isButtonEnabled(string $button, Editor $editor): bool {
    $settings = $editor->getSettings();
    foreach ($settings['toolbar']['rows'] as $row) {
      foreach ($row as $group) {
        foreach ($group['items'] as $group_button) {
          if ($group_button === $button) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

}
