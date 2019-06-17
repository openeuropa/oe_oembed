<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\Kernel;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_oembed\Oembed\OembedCacheableException;
use Drupal\Tests\oe_oembed\Traits\OembedTestTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests the OembedResolver class methods.
 */
class OembedResolverTest extends KernelTestBase {

  use OembedTestTrait;

  /**
   * The oEmbed resolver service.
   *
   * @var \Drupal\oe_oembed\Oembed\OembedResolverInterface
   */
  protected $oembedResolver;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    'oe_oembed',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig([
      'media',
      'field',
      'system',
      'image',
      'responsive_image',
    ]);
    $this->installEntitySchema('user');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->createMedia();

    $this->oembedResolver = $this->container->get('oe_oembed.oembed_resolver');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Test if image media requests get resolved properly.
   */
  public function testImageMedia(): void {
    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'image']);
    $media = reset($media);

    $expected = [
      'version' => '1.0',
      'type' => 'photo',
      'lang' => 'en',
      'width' => 200,
      'height' => 89,
    ];

    // Original image.
    $url = Url::fromUri('https://example.com/media/' . $media->uuid());
    $result = $this->oembedResolver->resolve($url);
    $expected['url'] = 'http://example.com/sites/default/files/example_1.jpeg';

    $this->assertEquals($expected, $result->getData());
    $this->assertEquals($media->id(), $result->getMedia()->id());

    // Using the default view mode should result in the same thing.
    $url = Url::fromUri('https://example.com/media/' . $media->uuid(), ['view_mode' => 'default']);
    $result = $this->oembedResolver->resolve($url);
    $this->assertEquals($expected, $result->getData());
    $this->assertEquals($media->id(), $result->getMedia()->id());

    // Using the full view mode which is configured with the thumbnail image
    // style.
    $url = Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'full']]);
    $result = $this->oembedResolver->resolve($url);
    $data = $result->getData();
    $this->assertContains('http://example.com/sites/default/files/styles/thumbnail/public/example_1.jpeg', $data['url']);
    $this->assertEquals(100, $data['width']);
    $this->assertEquals(45, $data['height']);
    $this->assertEquals($media->id(), $result->getMedia()->id());

    // Using the responsive image style.
    $url = Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'responsive']]);
    $result = $this->oembedResolver->resolve($url);
    $data = $result->getData();
    $crawler = new Crawler($data['html']);
    $picture = $crawler->filter('picture');
    $this->assertCount(1, $picture);
    $fallback_image = $picture->filter('img');
    $this->assertCount(1, $fallback_image);
    $this->assertContains('http://example.com/sites/default/files/styles/large/public/example_1.jpeg', $fallback_image->attr('src'));
    $this->assertEquals('rich', $data['type']);
    $this->assertEquals(200, $data['width']);
    $this->assertEquals(89, $data['height']);
    $this->assertEquals($media->id(), $result->getMedia()->id());

    // Causing exceptions.
    try {
      $url = Url::fromUri('https://example.com/media/invalid-uuid');
      $this->oembedResolver->resolve($url);
      $this->fail('The resolver did not throw an exception');
    }
    catch (OembedCacheableException $exception) {
      $this->assertEquals('The requested media entity was not found.', $exception->getMessage());
    }

    try {
      $url = Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'missing_view_mode']]);
      $this->oembedResolver->resolve($url);
      $this->fail('The resolver did not throw an exception');
    }
    catch (\Exception $exception) {
      $this->assertEquals('The requested entity view display does not exist.', $exception->getMessage());
    }

    try {
      $url = Url::fromUri('https://example.com/media/' . $media->uuid(), ['query' => ['view_mode' => 'missing_source']]);
      $this->oembedResolver->resolve($url);
      $this->fail('The resolver did not throw an exception');
    }
    catch (\Exception $exception) {
      $this->assertEquals('The media source field is not configured to show on this view mode.', $exception->getMessage());
    }
  }

  /**
   * Test if the remote video media requests get resolved properly.
   */
  public function testRemoteVideoMedia(): void {
    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'remote_video']);
    $media = reset($media);

    $url = Url::fromUri('https://example.com/media/' . $media->uuid());
    $result = $this->oembedResolver->resolve($url);
    $data = $result->getData();
    $crawler = new Crawler($data['html']);
    $iframe = $crawler->filter('iframe');

    $this->assertCount(1, $iframe);
    $this->assertContains(UrlHelper::encodePath('https://www.youtube.com/watch?v=1-g73ty9v04'), $iframe->attr('src'));
    $this->assertEquals('video', $data['type']);
    $this->assertEquals('en', $data['lang']);
    $this->assertEquals(400, $iframe->attr('width'));
    $this->assertEquals(250, $iframe->attr('height'));
    $this->assertEquals($media->id(), $result->getMedia()->id());
  }

  /**
   * Test if the file media requests get resolved properly.
   */
  public function testFileMedia(): void {
    $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'file']);
    $media = reset($media);

    $url = Url::fromUri('https://example.com/media/' . $media->uuid());
    $result = $this->oembedResolver->resolve($url);
    $data = $result->getData();

    $this->assertEquals('link', $data['type']);
    $this->assertEquals('3028', $data['size']);
    $this->assertEquals('application/pdf', $data['mime']);
    $this->assertEquals('en', $data['lang']);
    $this->assertEquals('http://example.com/sites/default/files/sample.pdf', $data['download']);
    $this->assertEquals($media->id(), $result->getMedia()->id());
  }

}
