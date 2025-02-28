<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_oembed\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_oembed\Traits\OembedTestTrait;

/**
 * Base class for all oEmbed functional tests.
 */
abstract class EmbedTestBase extends BrowserTestBase {

  use OembedTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'embed',
    'oe_media',
    'oe_oembed',
    'oe_media_oembed_mock',
    'oe_oembed_test',
    'node',
    'ckeditor5',
    'options',
  ];

  /**
   * The test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->basicSetup();
  }

}
