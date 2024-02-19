<?php

declare(strict_types=1);

namespace Drupal\oe_oembed_server\Oembed;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;

/**
 * Used for storing the result of the oEmbed resolver for a given URL.
 */
class OembedResolverResult implements RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * The URL to resolve.
   *
   * @var \Drupal\Core\Url
   */
  protected $url;

  /**
   * The resolved media entity.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $media;

  /**
   * The oEmbed return data from the resolved entity.
   *
   * @var array
   */
  protected $data = [];

  /**
   * OembedResolverResult constructor.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to resolve.
   * @param \Drupal\media\MediaInterface $media
   *   The resolved media entity.
   * @param array $data
   *   The oEmbed return data from the resolved entity.
   */
  public function __construct(Url $url, MediaInterface $media, array $data) {
    $this->url = $url;
    $this->media = $media;
    $this->data = $data;
  }

  /**
   * Returns the resolved media entity.
   *
   * @return \Drupal\media\MediaInterface
   *   The resolved media entity.
   */
  public function getMedia(): MediaInterface {
    return $this->media;
  }

  /**
   * Returns the URL to resolve.
   *
   * @return \Drupal\Core\Url
   *   The URL to resolve.
   */
  public function getUrl(): Url {
    return $this->url;
  }

  /**
   * Returns the oEmbed return data from the resolved entity.
   *
   * @return array
   *   The embed data.
   */
  public function getData(): array {
    return $this->data;
  }

}
