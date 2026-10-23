<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_oembed\Functional;

use Drupal\Core\Url;
use Drupal\editor\Entity\Editor;
use Drupal\embed\Entity\EmbedButton;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

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
      'editor' => 'ckeditor5',
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

  /**
   * Tests that the entity browser respects the embed button bundles.
   */
  public function testEntityBrowserBundles(): void {
    $oe_media_module_path = \Drupal::service('extension.list.module')->getPath('oe_media');
    $this->container->get('file_system')->copy($oe_media_module_path . '/tests/fixtures/example_1.jpeg', 'public://example_1.jpeg');
    $image = File::create([
      'uri' => 'public://example_1.jpeg',
    ]);
    $image->save();
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Test image media',
      'oe_media_image' => [$image],
    ]);
    $media->save();

    $media = Media::create([
      'bundle' => 'remote_video',
      'oe_media_oembed_video' => 'https://www.youtube.com/watch?v=OkPW9mK5Vw8',
    ]);
    $media->save();

    $this->container->get('file_system')->copy($oe_media_module_path . '/tests/fixtures/sample.pdf', 'public://sample.pdf');
    $file = File::create([
      'uri' => 'public://sample.pdf',
    ]);
    $file->save();
    $media = Media::create([
      'bundle' => 'document',
      'name' => 'Test document media',
      'oe_media_file' => [$file],
    ]);
    $media->save();

    $media_names = [];
    $media_ids = [];
    foreach (Media::loadMultiple() as $media) {
      $media_names[$media->bundle()] = $media->label();
      $media_ids[$media->bundle()] = $media->id();
    }

    $button = EmbedButton::load('media');
    $type_settings = $button->get('type_settings');
    $type_settings['entity_browser'] = 'embed_media_entity_browser_test';
    $button->set('type_settings', $type_settings)->save();
    $user = $this->drupalCreateUser([
      'use text format html',
      'view media',
      'access embed_media_entity_browser_test entity browser pages',
    ]);
    $this->drupalLogin($user);

    // The button allows only images and remote videos.
    $this->openEntityBrowserFromEmbedDialog();
    $this->assertSession()->pageTextContains($media_names['image']);
    $this->assertSession()->pageTextContains($media_names['remote_video']);
    $this->assertSession()->pageTextNotContains($media_names['document']);

    // Selections of other bundles are rejected, even if they bypass the view.
    $this->getEmbedDialog('html', 'media');
    $this->assertSession()->hiddenFieldExists('entity_browser[entity_ids]')->setValue('media:' . $media_ids['document']);
    $this->submitForm([], 'Next');
    $this->assertSession()->pageTextContains(sprintf('%s cannot be embedded with the Media button.', $media_names['document']));
    $this->getEmbedDialog('html', 'media');
    $this->assertSession()->hiddenFieldExists('entity_browser[entity_ids]')->setValue('media:' . $media_ids['image']);
    $this->submitForm([], 'Next');
    $this->assertSession()->pageTextNotContains('cannot be embedded');

    // Without bundles, the button allows all media.
    $type_settings['bundles'] = [];
    $button->set('type_settings', $type_settings)->save();
    $this->openEntityBrowserFromEmbedDialog();
    foreach ($media_names as $media_name) {
      $this->assertSession()->pageTextContains($media_name);
    }
  }

  /**
   * Opens the media embed dialog and the entity browser it renders.
   */
  protected function openEntityBrowserFromEmbedDialog(): void {
    $this->getEmbedDialog('html', 'media');
    $this->assertSession()->statusCodeEquals(200);
    $uuid = $this->assertSession()->elementExists('css', 'a.entity-browser-handle')->getAttribute('data-uuid');
    $this->drupalGet(Url::fromRoute('entity_browser.embed_media_entity_browser_test', [], ['query' => ['uuid' => $uuid]]));
    $this->assertSession()->statusCodeEquals(200);
  }

}
