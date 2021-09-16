<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed_server_test\EventSubscriber;

use Drupal\oe_oembed_server\Event\OembedResolverSource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test event subscriber for the OembedResolverSource event.
 */
class OembedTestSourceSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      OembedResolverSource::OEMBED_RESOLVER_SOURCE => 'resolveSource',
    ];
  }

  /**
   * Resolve a given source.
   *
   * @param \Drupal\oe_oembed_server\Event\OembedResolverSource $event
   *   The event.
   */
  public function resolveSource(OembedResolverSource $event): void {
    $media = $event->getMedia();
    $source = $media->getSource();
    if ($source->getPluginId() !== 'test') {
      return;
    }

    $data = [
      'version' => '1.0',
      'type' => 'rich',
      'html' => '<p>This is data resolved from a test source</p>',
    ];

    $event->setData($data);
    $event->stopPropagation();
  }

}
