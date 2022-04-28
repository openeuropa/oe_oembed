<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Plugin\CKEditorPlugin;

use Drupal\Core\Entity\Query\QueryInterface;
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
  protected RouteMatchInterface $currentRouteMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, QueryInterface $embed_button_query, RouteMatchInterface $current_route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $embed_button_query);
    $this->currentRouteMatch = $current_route_match;
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
      $container->get('current_route_match')
    );
  }

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
      'current_route' => $this->currentRouteMatch->getRouteName(),
      'current_route_parameters' => $this->currentRouteMatch->getRawParameters()->all(),
      'Oembed_buttons' => $this->getButtons(),
    ];
  }

}
