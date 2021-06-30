<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\modules\oe_oembed_test\src\EventSubscriber;

use Drupal\oe_oembed\Event\OembedResolverAlter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test event subscriber for the OembedResolverAlter event.
 */
class OembedTestAlterSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
   * @param \Drupal\oe_oembed\Event\OembedResolverAlter $event
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
