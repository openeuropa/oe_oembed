<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_oembed\FunctionalJavascript;

use Drupal\embed\Entity\EmbedButton;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the creation of an embed button using the oEmbed type.
 */
class EmbedButtonAdminTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'oe_media',
    'oe_oembed',
    'node',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
  }

  /**
   * Tests we can create an embed button in the UI using the oEmbed type.
   */
  public function testCreateEmbedButton(): void {
    $user = $this->drupalCreateUser([
      'administer embed buttons',
    ]);

    $this->drupalLogin($user);
    $this->drupalGet('admin/config/content/embed/button/add');

    $this->getSession()->getPage()->fillField('label', 'Test button no bundles');
    $this->assertSession()->waitForElementVisible('css', '.machine-name-value');

    $this->getSession()->getPage()->selectFieldOption('type_id', 'oe_oembed_entities');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $select = $this->assertSession()->selectExists('Entity type');
    $this->assertEquals('', $select->getValue());
    $expected = [
      '- Select -' => '- Select -',
      'file' => 'File',
      'media' => 'Media',
      'node' => 'Content',
      'path_alias' => 'URL alias',
      'user' => 'User',
    ];
    $this->assertEquals($expected, $this->getOptions($select));

    // The entity type select is required.
    $this->disableNativeBrowserRequiredFieldValidation();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Entity type field is required.');

    // Select an entity that doesn't support bundles.
    $this->getSession()->getPage()->selectFieldOption('Entity type', 'user');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals('user', $this->assertSession()->selectExists('Entity type')->getValue());
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('The embed button Test button no bundles has been added.');

    /** @var \Drupal\embed\EmbedButtonInterface $button */
    $button = EmbedButton::load('test_button_no_bundles');
    $this->assertEquals('user', $button->getTypeSetting('entity_type'));
    $this->assertEmpty($button->getTypeSetting('bundles'));

    // Create a new button using an entity type with bundles.
    $this->drupalGet('admin/config/content/embed/button/add');
    $this->getSession()->getPage()->fillField('label', 'Test button with bundles');
    $this->getSession()->getPage()->selectFieldOption('type_id', 'oe_oembed_entities');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Entity type', 'media');
    $this->assertSession()->assertWaitOnAjaxRequest();
    foreach (['Document', 'Image', 'Remote video'] as $label) {
      $this->assertSession()->fieldExists($label);
      $this->assertSession()->checkboxNotChecked($label);
    }
    $this->getSession()->getPage()->selectFieldOption('Entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->checkboxNotChecked('Basic page');
    $this->getSession()->getPage()->checkField('Basic page');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('The embed button Test button with bundles has been added.');
    $button = EmbedButton::load('test_button_with_bundles');
    $this->assertEquals('node', $button->getTypeSetting('entity_type'));
    $this->assertEquals(['page'], $button->getTypeSetting('bundles'));
  }

  /**
   * Disables the native browser validation for required fields.
   */
  protected function disableNativeBrowserRequiredFieldValidation() {
    $this->getSession()->executeScript("jQuery(':input[required]').prop('required', false);");
  }

}
