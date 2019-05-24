<?php

namespace Drupal\Tests\oe_oembed\Browser;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests regarding the responses gotten from the oembed route.
 */
class OembedRouteTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'oe_oembed',
  ];

  /**
   * Tests oembed route returns a correct json.
   */
  public function testOembedRoute() {

    $this->drupalGet('oembed');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is an empty reaction rule listing.
    $this->assertSession()->responseContains('{"status":"OK"}');
  }

}
