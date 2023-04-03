<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed_test\EventSubscriber;

use Drupal\entity_browser\Events\AlterEntityBrowserDisplayData;
use Drupal\entity_browser\Events\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test event subscriber for the OembedResolverAlter event.
 */
class OembedTestSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      Events::ALTER_BROWSER_DISPLAY_DATA => 'alterData',
    ];
  }

  /**
   * Store some data for testing purposes in state.
   *
   * @param \Drupal\entity_browser\Events\AlterEntityBrowserDisplayData $event
   *   The event.
   */
  public function alterData(AlterEntityBrowserDisplayData $event): void {
    \Drupal::state()->set('oe_oembed_test.embed_url.current_route_data', \Drupal::requestStack()->getCurrentRequest()->request->all('editor_object'));
  }

}
