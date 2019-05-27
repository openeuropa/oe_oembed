<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\Kernel;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests the OembedResolver class methods.
 */
class OemberResolverTest extends KernelTestBase {
  use MediaTypeCreationTrait;
  /**
   * The oEmbed resolver service that we need to test.
   *
   * @var \Drupal\oe_oembed\Oembed\OembedResolverInterface
   */
  protected $oembedResolver;

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  public static $modules = [
    'field',
    'file',
    'image',
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

    $this->installConfig(['media']);
    $this->installConfig(['field']);
    $this->installConfig(['system']);
    $this->installConfig(['image']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->oembedResolver = \Drupal::service('oe_oembed.oembed_resolver');
  }

  /**
   * Test if Image media requests get resolved properly.
   */
  public function testImageMedia() {
    $media_type = $this->createMediaType('image');
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $image = File::create([
      'uri' => 'public://test-image.png',
    ]);
    $image->save();

    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => $this->randomMachineName(),
      $source_field->getName() => [$image],
    ]);
    $media->save();

    $url = Url::fromUri('external:media/' . $media->uuid());
    $json = $this->oembedResolver->resolve($url);

    $this->assertEqual($json['url'], file_create_url($image->getFileUri()));
  }

  /**
   * Test if oEmbed media requests get resolved properly.
   */
  public function testOembedMedia() {
    $media_type = $this->createMediaType('oembed:video');
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);

    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => $this->randomMachineName(),
      $source_field->getName() => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    ]);
    $media->save();

    $url = Url::fromUri('external:media/' . $media->uuid());
    $json = $this->oembedResolver->resolve($url);

    $this->assertEqual($json['html'], '<iframe width="480" height="270" src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>');
  }

  /**
   * Test if File media requests get resolved properly.
   */
  public function testFileMedia() {
    $media_type = $this->createMediaType('file');
    $media_source = $media_type->getSource();
    $source_field = $media_source->getSourceFieldDefinition($media_type);

    $file = File::create([
      'uri' => 'public://test-file.txt',
    ]);
    $file->save();

    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => $this->randomMachineName(),
      $source_field->getName() => [$file],
    ]);
    $media->save();

    $url = Url::fromUri('external:media/' . $media->uuid());
    $json = $this->oembedResolver->resolve($url);

    $this->assertEqual($json['html'], '<iframe width="480" height="270" src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>');
  }

}
