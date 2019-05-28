<?php

namespace Drupal\Tests\oe_oembed\Browser;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the oEmbed provider responses.
 */
class OembedRouteTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'oe_oembed',
  ];

  /**
   * Tests that the oEmbed route returns the correct json.
   */
  public function testOembedRoute() {

    $this->drupalGet('oembed');
    $this->assertSession()->statusCodeEquals(200);

    // Test that the contents of the JSON are correct.
    $this->assertSession()->responseContains('{"status":"OK"}');
  }

}
