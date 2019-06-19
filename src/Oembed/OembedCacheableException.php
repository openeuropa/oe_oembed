<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Oembed;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;

/**
 * Exception that can contain cache metadata.
 */
class OembedCacheableException extends \Exception implements RefinableCacheableDependencyInterface {
  use RefinableCacheableDependencyTrait;

}
