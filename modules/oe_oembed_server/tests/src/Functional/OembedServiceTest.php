<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_oembed_server\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_oembed\Traits\OembedTestTrait;

/**
 * Tests the oEmbed provider responses.
 */
class OembedServiceTest extends BrowserTestBase {

  use OembedTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'image',
    'responsive_image',
    'breakpoint',
    'media',
    'oe_oembed_server',
    'user',
    'media_test_source',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createMedia();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests that the oEmbed route returns the correct responses.
   */
  public function testOembedRoute(): void {
    $this->drupalGet('oembed');
    // Without passing a Resource URL, we should not have access to the
    // endpoint.
    $this->assertSession()->statusCodeEquals(403);

    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'image']);
    $media = reset($media);
    $source = $media->getSource();

    // Valid with image media.
    $tests = [
      Url::fromUri('https://example.com/media/' . $media->uuid()),
      Url::fromUri('https://example.com/media/' . $media->uuid(), ['view_mode' => 'default']),
      Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'full']]),
      Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'responsive']]),
    ];

    /** @var \Drupal\Core\Url $test */
    foreach ($tests as $test) {
      $response = $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => $test->toString()]]));
      $this->assertSession()->statusCodeEquals(200);
      $decoded = json_decode($response);
      $this->assertEquals('1.0', $decoded->version);
    }

    // Invalid with image media.
    $tests = [
      Url::fromUri('https://example.com/media/invalid-uuid')->toString() => 403,
      Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'missing_view_mode']])->toString() => 404,
      Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'missing_source']])->toString() => 404,
    ];

    foreach ($tests as $url => $code) {
      $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => $url]]));
      $this->assertSession()->statusCodeEquals($code);
    }

    // Delete some dependencies to ensure the caching works correctly.
    $this->entityTypeManager->getStorage('image_style')->load('thumbnail')->delete();
    $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'full']])->toString()]]));
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->responseContains('The media source field is not configured to show on this view mode.');

    $this->entityTypeManager->getStorage('responsive_image_style')->load('responsive_style')->delete();
    $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'responsive']])->toString()]]));
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->responseContains('The media source field is not configured to show on this view mode.');

    $this->entityTypeManager->getStorage('entity_view_display')->load('media.image.full')->delete();
    $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'full']])->toString()]]));
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->responseContains('The requested entity view display does not exist.');

    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')->load('image');
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface $display */
    $display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.image.responsive');
    $content = $display->get('content');
    unset($content[$source_field->getName()]);
    $display->set('content', $content);
    $display->save();
    $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'responsive']])->toString()]]));
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->responseContains('The media source field is not configured to show on this view mode.');

    $source_field_value = $source->getSourceFieldValue($media);
    $image = $this->entityTypeManager->getStorage('file')->load($source_field_value);
    $image->delete();
    $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => Url::fromUri('https://example.com/media/' . $media->uuid())->toString()]]));
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->responseContains('The source image is missing.');

    // Testing the video media.
    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'remote_video']);
    $media = reset($media);

    // Valid with remote video media.
    $response = $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => Url::fromUri('https://example.com/media/' . $media->uuid())->toString()]]));
    $this->assertSession()->statusCodeEquals(200);
    $decoded = json_decode($response);
    $this->assertEquals('1.0', $decoded->version);
    $this->assertEquals('video', $decoded->type);

    // Invalid with remote video media.
    $tests = [
      Url::fromUri('https://example.com/media/invalid-uuid')->toString() => 403,
      Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'missing_view_mode']])->toString() => 404,
      Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'missing_source']])->toString() => 404,
    ];

    foreach ($tests as $url => $code) {
      $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => $url]]));
      $this->assertSession()->statusCodeEquals($code);
    }

    // Testing the file media.
    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'file']);
    $media = reset($media);
    $source = $media->getSource();

    // Valid with file media.
    $response = $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => Url::fromUri('https://example.com/media/' . $media->uuid())->toString()]]));
    $this->assertSession()->statusCodeEquals(200);
    $decoded = json_decode($response);
    $this->assertEquals('1.0', $decoded->version);
    $this->assertEquals('link', $decoded->type);

    // Invalid with remote video media.
    $tests = [
      Url::fromUri('https://example.com/media/invalid-uuid')->toString() => 403,
      Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'missing_view_mode']])->toString() => 404,
      Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'missing_source']])->toString() => 404,
    ];

    foreach ($tests as $url => $code) {
      $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => $url]]));
      $this->assertSession()->statusCodeEquals($code);
    }

    // Delete the file behind the media entity to ensure a 404 response.
    $source_field_value = $source->getSourceFieldValue($media);
    $file = $this->entityTypeManager->getStorage('file')->load($source_field_value);
    $file->delete();

    $this->drupalGet(Url::fromRoute('oe_oembed_server.oembed', [], ['query' => ['url' => Url::fromUri('https://example.com/media/' . $media->uuid())->toString()]]));
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->responseContains('The source file is missing.');
  }

}
