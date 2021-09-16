<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed_server_test\EventSubscriber;

use Drupal\oe_oembed_server\Event\OembedResolverAlter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test event subscriber for the OembedResolverAlter event.
 */
class OembedTestAlterSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      OembedResolverAlter::OEMBED_RESOLVER_ALTER => 'alterResolver',
    ];
  }

  /**
   * Handles the alteration.
   *
   * @param \Drupal\oe_oembed_server\Event\OembedResolverAlter $event
   *   The event.
   */
  public function alterResolver(OembedResolverAlter $event): void {
    $params = $event->getQueryParams();
    if (isset($params['alter'])) {
      $data = $event->getData();
      $data['alter'] = 'altered';
      $event->setData($data);
    }
  }

}
