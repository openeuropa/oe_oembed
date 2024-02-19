<?php

declare(strict_types=1);

namespace Drupal\oe_oembed;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Resolves the UUID from an oembed URL.
 */
class OembedDataResolver {

  /**
   * The oembed settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new OembedDataResolver object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('oe_oembed.settings');
  }

  /**
   * Resolves the UUID from the oembed URL.
   *
   * @param string $oembed
   *   The oembed value.
   *
   * @return string|null
   *   The resolved UUID.
   */
  public function resolveUuid(string $oembed): ?string {
    $parsed = UrlHelper::parse($oembed);

    $service_url = $this->config->get('service_url');
    $resource_base_url = $this->config->get('resource_base_url');

    if (!isset($parsed['path']) || $parsed['path'] !== $service_url) {
      return NULL;
    }

    if (!isset($parsed['query']['url']) || strpos($parsed['query']['url'], $resource_base_url) === FALSE) {
      return NULL;
    }

    $parsed_resource_url = UrlHelper::parse($parsed['query']['url']);
    $regex = '/' . Uuid::VALID_PATTERN . '/';
    preg_match($regex, $parsed_resource_url['path'], $matches);
    if (!$matches) {
      return NULL;
    }

    return $matches[0];
  }

  /**
   * Resolves the entity type from the oembed URL.
   *
   * @param string $oembed
   *   The oembed value.
   *
   * @return string|null
   *   The resolved entity type.
   */
  public function resolveEntityType(string $oembed): ?string {
    $uuid = $this->resolveUuid($oembed);
    if (!$uuid) {
      return NULL;
    }

    $parsed = UrlHelper::parse($oembed);
    $resource_base_url = $this->config->get('resource_base_url');
    $parsed_resource_url = UrlHelper::parse($parsed['query']['url']);
    return trim(str_replace([$resource_base_url, $uuid], ['', ''], $parsed_resource_url['path']), '/');
  }

  /**
   * Resolves the view mode from the oembed URL.
   *
   * @param string $oembed
   *   The oembed value.
   *
   * @return string
   *   The resolved view mode.
   */
  public function resolveViewMode(string $oembed): string {
    $parsed = UrlHelper::parse($oembed);
    $parsed_resource_url = UrlHelper::parse($parsed['query']['url']);
    return $parsed_resource_url['query']['view_mode'] ?? 'default';
  }

}
