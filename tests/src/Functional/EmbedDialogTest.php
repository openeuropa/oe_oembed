<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\Functional;

use Drupal\editor\Entity\Editor;

/**
 * Tests the embed dialog.
 */
class EmbedDialogTest extends EmbedTestBase {

  /**
   * Tests access and configuration of the embed dialog.
   */
  public function testEmbedDialog(): void {
    // Ensure that the route is not accessible without specifying all the
    // parameters.
    $this->getEmbedDialog();
    $this->assertSession()->statusCodeEquals(404);
    $this->getEmbedDialog('html');
    $this->assertSession()->statusCodeEquals(404);

    // Ensure that the route is not accessible with an invalid embed button.
    $this->getEmbedDialog('html', 'invalid_button');
    $this->assertSession()->statusCodeEquals(404);

    // Ensure that the route is not accessible with text format without the
    // button configured.
    foreach (['media', 'node'] as $button_id) {
      $this->getEmbedDialog('plain_text', $button_id);
      $this->assertSession()->statusCodeEquals(404);
    }

    // Add an empty configuration for the plain_text editor configuration.
    $editor = Editor::create([
      'format' => 'plain_text',
      'editor' => 'ckeditor',
    ]);
    $editor->save();
    foreach (['media', 'node'] as $button_id) {
      $this->getEmbedDialog('plain_text', $button_id);
      $this->assertSession()->statusCodeEquals(403);

      // Ensure that the route is accessible with a valid embed button.
      $this->getEmbedDialog('html', $button_id);
      $this->assertSession()->statusCodeEquals(200);

      // Ensure form structure of the 'select' step and submit form.
      $field = $this->getSession()->getPage()->findField('entity_id');
      $this->assertEquals('', $field->getValue());
      $field = $this->getSession()->getPage()->find('xpath', '//input[contains(@class, "button--primary")]');
      $this->assertEquals('Next', $field->getValue());
    }
  }

}
