<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Oembed;

use Drupal\Core\Url;

/**
 * Oembed request resolver interface.
 */
interface OembedResolverInterface {

  /**
   * Resolve the request into a valid oEmbed json response.
   *
   * @param \Drupal\Core\Url $url
   *   The requested url.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The access result.
   */
  public function resolve(Url $url);

}
