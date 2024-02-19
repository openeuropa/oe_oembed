<?php

declare(strict_types=1);

namespace Drupal\oe_oembed_server\Oembed;

use Drupal\Core\Url;

/**
 * Oembed request resolver interface.
 */
interface OembedResolverInterface {

  /**
   * Resolve the request into a valid oEmbed result.
   *
   * @param \Drupal\Core\Url $url
   *   The requested url.
   *
   * @return OembedResolverResult
   *   The resolved result.
   */
  public function resolve(Url $url): OembedResolverResult;

}
