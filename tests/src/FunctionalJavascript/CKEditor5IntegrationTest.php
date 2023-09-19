<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\editor\EditorInterface;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\JSWebAssert;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\oe_oembed\Traits\CKEditor5TestTrait as ExtraCKEditor5TestTrait;
use Drupal\Tests\oe_oembed\Traits\MediaCreationTrait;
use Symfony\Component\Validator\ConstraintViolation;
use WebDriver\Exception;

/**
 * Tests CKEditor integration.
 */
class CKEditor5IntegrationTest extends WebDriverTestBase {

  use CKEditor5TestTrait;
  use ExtraCKEditor5TestTrait;
  use MediaCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_media',
    'oe_oembed',
    'oe_oembed_test',
    'oe_media_oembed_mock',
    'node',
    'ckeditor5',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The editor instance.
   *
   * @var \Drupal\editor\EditorInterface
   */
  protected EditorInterface $editor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<br> <strong> <p data-oembed data-display-as> <a data-oembed data-display-as href>',
          ],
        ],
        'oe_oembed_filter' => [
          'status' => TRUE,
        ],
      ],
    ])->save();

    $this->editor = Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'test_format',
      'settings' => [
        'toolbar' => [
          'items' => [
            // @todo remove
            'sourceEditing',
            'bold',
          ],
        ],
        'plugins' => [
          'ckeditor5_sourceEditing' => [
            'allowed_tags' => [],
          ],
        ],
      ],
      'image_upload' => [
        'status' => FALSE,
      ],
    ]);
    $this->editor->save();
    // Checks correct configuration.
    $this->validateEditorFormatPair();

    node_add_body_field(NodeType::load('page'));

    $this->user = $this->drupalCreateUser([
      'access content',
      'bypass node access',
      'use text format test_format',
      'create image media',
      'create remote_video media',
    ]);
  }

  /**
   * Tests the upcast from existing content.
   */
  public function testUpcast(): void {
    $embeddable = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Content to embed',
    ]);
    $embeddable->save();

    $host = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Host page',
      'body' => [
        'value' => $this->getBlockEmbedString('node', 'default', $embeddable->uuid(), $embeddable->label()),
        'format' => 'test_format',
      ],
    ]);
    $host->save();

    $this->drupalLogin($this->user);
    $edit_url = $host->toUrl('edit-form');

    $this->drupalGet($edit_url);
    $this->waitForEditor();
    $assert_session = $this->assertSession();
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('p.ck-oe-oembed.ck-widget > span > a[href="https://data.ec.europa.eu/ewp/node/%s"]', $embeddable->uuid()), $editor);
    $this->assertEquals($embeddable->label(), $link->getHtml());
    // Make sure that the link is not clickable.
    $this->attemptLinkClick($link);
    $this->assertEquals($edit_url->toString(), $this->getUrl());
    // Check that the HTML generated on downcast is the same.
    $this->assertEquals($host->get('body')->value, $this->getEditorDataAsHtmlString());

    $host->set('body', [
      'value' => $this->getInlineEmbedString('node', 'default', $embeddable->uuid(), $embeddable->label()),
      'format' => 'test_format',
    ])->save();
    $this->drupalGet($edit_url);
    $this->waitForEditor();
    $assert_session = $this->assertSession();
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('span.ck-oe-oembed.ck-widget > a[href="https://data.ec.europa.eu/ewp/node/%s"]', $embeddable->uuid()), $editor);
    $this->assertEquals($embeddable->label(), $link->getHtml());
    // Make sure that the link is not clickable.
    $this->attemptLinkClick($link);
    $this->assertEquals($edit_url->toString(), $this->getUrl());
    // Check that the HTML generated on downcast is the same, with an additional
    // <p> wrapper added by the editor itself.
    $this->assertEquals('<p>' . $host->get('body')->value . '</p>', $this->getEditorDataAsHtmlString());
  }

  /**
   * Tests the embed procedure with a single button.
   */
  public function testBlockEmbed(): void {
    // Enable the node embed button.
    $this->editor->setSettings(array_merge_recursive($this->editor->getSettings(), [
      'toolbar' => [
        'items' => [
          'node',
        ],
      ],
    ]))->save();
    $this->validateEditorFormatPair();

    $node = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Content to embed',
    ]);
    $node->save();

    $host = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Host page',
      'body' => [
        'value' => '<p>First paragraph</p><p>Pre text <strong>to replace</strong> after text</p><p>Last paragraph</p>',
        'format' => 'test_format',
      ],
    ]);
    $host->save();

    $this->drupalLogin($this->user);
    $this->drupalGet($host->toUrl('edit-form'));
    $this->waitForEditor();
    // Select the <strong> tag.
    $this->selectTextInsideElement('strong');
    $this->embedEntityWithSimpleModal('Node', $node);
    $assert_session = $this->assertSession();
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('p.ck-oe-oembed.ck-widget > span > a[href="https://data.ec.europa.eu/ewp/node/%s"]', $node->uuid()), $editor);
    $this->assertEquals($node->label(), $link->getHtml());
    // The strong tag has been replaced by the embedded entity markup.
    $this->assertEquals('<p>First paragraph</p><p>Pre text&nbsp;</p>' . $this->getBlockEmbedString('node', 'embed', $node->uuid(), $node->label()) . '<p>&nbsp;after text</p><p>Last paragraph</p>', $this->getEditorDataAsHtmlString());

    // Create a new node to embed.
    $another_embeddable = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Different page to embed',
    ]);
    $another_embeddable->save();

    // Edit the previous embedded node and replace it with the new one.
    $assert_session->elementExists('css', 'p.ck-oe-oembed.ck-widget', $editor)->click();
    $this->editEmbeddedEntityWithSimpleModal($node, $another_embeddable);
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('p.ck-oe-oembed.ck-widget > span > a[href="https://data.ec.europa.eu/ewp/node/%s"]', $another_embeddable->uuid()), $editor);
    $this->assertEquals($another_embeddable->label(), $link->getHtml());
    $this->assertEquals('<p>First paragraph</p><p>Pre text&nbsp;</p>' . $this->getBlockEmbedString('node', 'embed', $another_embeddable->uuid(), $another_embeddable->label()) . '<p>&nbsp;after text</p><p>Last paragraph</p>', $this->getEditorDataAsHtmlString());

    // Check that the correct button gets reassigned to the element on edit.
    $assert_session->buttonExists('Save')->press();
    $assert_session->statusMessageContains('Basic page Host page has been updated.');
    $this->drupalGet($host->toUrl('edit-form'));
    $assert_session->elementExists('css', 'p.ck-oe-oembed.ck-widget', $editor)->click();
    $this->getBalloonButton('Edit')->click();
    $modal = $assert_session->waitForElement('css', '.oe-oembed-entities-select-dialog');
    $this->assertStringContainsString('Selected entity', $modal->getText());
    $this->assertNotEmpty($modal->findLink($another_embeddable->label()));
  }

  /**
   * Tests embedding of "inline" view modes.
   */
  public function testInlineEmbed(): void {
    $view_display = EntityViewDisplay::load('node.page.inline');
    $view_display->setThirdPartySetting('oe_oembed', 'inline', TRUE);
    $view_display->setThirdPartySetting('oe_oembed', 'embeddable', TRUE);
    $view_display->save();

    $this->editor->setSettings(array_merge_recursive($this->editor->getSettings(), [
      'toolbar' => [
        'items' => [
          'node',
        ],
      ],
    ]))->save();
    $this->validateEditorFormatPair();

    $node = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Content to embed',
    ]);
    $node->save();

    $host = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Host page',
      'body' => [
        'value' => '<p>Some text in the editor</p>',
        'format' => 'test_format',
      ],
    ]);
    $host->save();

    $this->drupalLogin($this->user);
    $this->drupalGet($host->toUrl('edit-form'));
    $this->waitForEditor();
    $this->placeCursorAtBoundaryOfElement('p', FALSE);
    $this->embedEntityWithSimpleModal('Node', $node, 'Inline');
    $assert_session = $this->assertSession();
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('span.ck-oe-oembed.ck-widget > a[href="https://data.ec.europa.eu/ewp/node/%s"]', $node->uuid()), $editor);
    $this->assertEquals($node->label(), $link->getHtml());
    $this->assertEquals('<p>Some text in the editor' . $this->getInlineEmbedString('node', 'inline', $node->uuid(), $node->label()) . '</p>', $this->getEditorDataAsHtmlString());

    // Create a new node to embed.
    $another_embeddable = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Different page to embed',
    ]);
    $another_embeddable->save();

    // Edit the previous embedded node and replace it with the new one.
    $assert_session->elementExists('css', 'span.ck-oe-oembed.ck-widget', $editor)->click();
    $this->editEmbeddedEntityWithSimpleModal($node, $another_embeddable);
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('span.ck-oe-oembed.ck-widget > a[href="https://data.ec.europa.eu/ewp/node/%s"]', $another_embeddable->uuid()), $editor);
    $this->assertEquals($another_embeddable->label(), $link->getHtml());
    $this->assertEquals('<p>Some text in the editor' . $this->getInlineEmbedString('node', 'inline', $another_embeddable->uuid(), $another_embeddable->label()) . '</p>', $this->getEditorDataAsHtmlString());

    // Test that the inline widget can be put inside block elements, but not
    // inside inline elements such as links.
    $host->set('body', [
      'value' => '<p><a href="https://www.example.com">A link with <strong>text</strong> in it.</a></p>',
      'format' => 'test_format',
    ])->save();
    $this->drupalGet($host->toUrl('edit-form'));
    $this->waitForEditor();
    $this->selectTextInsideElement('strong');
    $this->embedEntityWithSimpleModal('Node', $node, 'Inline');
    // The splitting of the link is done by CKEditor, not by our plugin. It
    // appears that the link loses the href attribute in the process.
    $this->assertEquals(
      '<p><a>A link with&nbsp;</a>' . $this->getInlineEmbedString('node', 'inline', $node->uuid(), $node->label()) . '<a>&nbsp;in it.</a></p>',
      $this->getEditorDataAsHtmlString()
    );
  }

  /**
   * Tests embed functionality when multiple buttons are available.
   */
  public function testEmbedMultipleButtons(): void {
    $this->editor->setSettings(array_merge_recursive($this->editor->getSettings(), [
      'toolbar' => [
        'items' => [
          'node',
          'media',
        ],
      ],
    ]))->save();
    $this->validateEditorFormatPair();

    $node = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Content to embed',
    ]);
    $node->save();
    $video = $this->createRemoteVideoMedia();

    $host = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Host page',
    ]);
    $host->save();

    $this->drupalLogin($this->user);
    $this->drupalGet($host->toUrl('edit-form'));
    $this->waitForEditor();
    // Embed the remote video media.
    $this->embedEntityWithSimpleModal('Media', $video);
    $assert_session = $this->assertSession();
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('p.ck-oe-oembed.ck-widget > span > a[href="https://data.ec.europa.eu/ewp/media/%s"]', $video->uuid()), $editor);
    $this->assertEquals($video->label(), $link->getHtml());
    $this->assertEquals($this->getBlockEmbedString('media', 'embed', $video->uuid(), $video->label()), $this->getEditorDataAsHtmlString());

    // Block widgets get a newline button to add space before or after the
    // widget itself. Press the button to space after.
    $video_widget = $assert_session->elementExists('xpath', $this->cssSelectToXpath('p.ck-oe-oembed.ck-widget', TRUE, 'ancestor::'), $link);
    $assert_session->elementExists('css', 'div.ck-widget__type-around__button_after', $video_widget)->click();

    $this->embedEntityWithSimpleModal('Node', $node);
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('p.ck-oe-oembed.ck-widget > span > a[href="https://data.ec.europa.eu/ewp/node/%s"]', $node->uuid()), $editor);
    $this->assertEquals($node->label(), $link->getHtml());
    $this->assertEquals(
      $this->getBlockEmbedString('media', 'embed', $video->uuid(), $video->label()) . $this->getBlockEmbedString('node', 'embed', $node->uuid(), $node->label()),
      $this->getEditorDataAsHtmlString()
    );

    // Test that the correct button gets passed back to the modal.
    $video_widget->click();
    $this->editEmbeddedEntityWithSimpleModal($video);
    $this->assertEquals(
      $this->getBlockEmbedString('media', 'embed', $video->uuid(), $video->label()) . $this->getBlockEmbedString('node', 'embed', $node->uuid(), $node->label()),
      $this->getEditorDataAsHtmlString()
    );

    $node_widget = $assert_session->elementExists('xpath', $this->cssSelectToXpath('p.ck-oe-oembed.ck-widget', TRUE, 'ancestor::'), $link);
    $node_widget->click();
    $this->editEmbeddedEntityWithSimpleModal($node);
    $this->assertEquals(
      $this->getBlockEmbedString('media', 'embed', $video->uuid(), $video->label()) . $this->getBlockEmbedString('node', 'embed', $node->uuid(), $node->label()),
      $this->getEditorDataAsHtmlString()
    );

    // Test that the correct button is determined from the list of available
    // buttons after editing a saved content.
    $assert_session->buttonExists('Save')->press();
    $assert_session->statusMessageContains('Basic page Host page has been updated.');
    $this->drupalGet($host->toUrl('edit-form'));
    $video_widget->click();
    $this->getBalloonButton('Edit')->click();
    $modal = $assert_session->waitForElement('css', '.oe-oembed-entities-select-dialog');
    $this->assertStringContainsString('Selected entity', $modal->getText());
    $this->assertNotEmpty($modal->findLink($video->label()));
    $modal->find('css', 'button.ui-dialog-titlebar-close')->press();
    $assert_session->waitForElementRemoved('css', 'oe-oembed-entities-select-dialog');

    $node_widget->click();
    $this->getBalloonButton('Edit')->click();
    $modal = $assert_session->waitForElement('css', '.oe-oembed-entities-select-dialog');
    $this->assertStringContainsString('Selected entity', $modal->getText());
    $this->assertNotEmpty($modal->findLink($node->label()));
    $modal->find('css', 'button.ui-dialog-titlebar-close')->press();
    $assert_session->waitForElementRemoved('css', 'oe-oembed-entities-select-dialog');
  }

  /**
   * Validates that editor and text format in the test are configured correctly.
   */
  protected function validateEditorFormatPair(): void {
    $this->assertSame([], array_map(
      function (ConstraintViolation $v) {
        return (string) $v->getMessage();
      },
      iterator_to_array(CKEditor5::validatePair(
        Editor::load('test_format'),
        FilterFormat::load('test_format')
      ))
    ));
  }

  /**
   * Attempts to click on a link.
   *
   * Useful to test that a link is not clickable.
   * This method is slow: the driver will attempt multiple times to click
   * the element.
   * See \Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver::click()
   *
   * @param \Behat\Mink\Element\NodeElement $link
   *   The link to click.
   */
  protected function attemptLinkClick(NodeElement $link): void {
    try {
      $link->click();
    }
    catch (Exception $e) {
      if (JSWebAssert::isExceptionNotClickable($e)) {
        return;
      }

      throw $e;
    }
  }

  /**
   * Embeds an entity in the editor.
   *
   * @param string $button_label
   *   The label of the button.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to embed.
   * @param string|null $display_mode
   *   The display mode to use. If left empty, it will assert that no display
   *   mode field is shown.
   */
  protected function embedEntityWithSimpleModal(string $button_label, EntityInterface $entity, string $display_mode = NULL): void {
    $this->pressEditorButton($button_label);
    $assert_session = $this->assertSession();
    $modal = $assert_session->waitForElement('css', '.oe-oembed-entities-select-dialog');
    $assert_session->fieldExists('entity_id', $modal)->setValue(sprintf('%s (%s)', $entity->label(), $entity->id()));
    $assert_session->elementExists('css', 'button.js-button-next', $modal)->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertStringContainsString('Selected entity', $modal->getText());
    $this->assertNotEmpty($modal->findLink($entity->label()));

    if ($display_mode !== NULL) {
      $assert_session->selectExists('Display as')->selectOption($display_mode);
    }
    else {
      $assert_session->fieldNotExists('Display as');
    }

    $assert_session->elementExists('css', 'button.button--primary', $modal)->press();
    $assert_session->assertWaitOnAjaxRequest();
  }

  /**
   * Open the edit modal for the currently selected embedded element.
   *
   * @param \Drupal\Core\Entity\EntityInterface $current_entity
   *   The current entity embedded. Useful to run assertions.
   * @param \Drupal\Core\Entity\EntityInterface|null $new_entity
   *   The new entity to replace. When left empty, no changes are done.
   * @param string|null $display_mode
   *   The display mode to use. If left empty, no changes are done.
   */
  protected function editEmbeddedEntityWithSimpleModal(EntityInterface $current_entity, ?EntityInterface $new_entity = NULL, string $display_mode = NULL): void {
    $this->getBalloonButton('Edit')->click();
    $assert_session = $this->assertSession();
    $modal = $assert_session->waitForElement('css', '.oe-oembed-entities-select-dialog');
    $this->assertStringContainsString('Selected entity', $modal->getText());
    $this->assertNotEmpty($modal->findLink($current_entity->label()));
    $assert_session->elementExists('xpath', '//button[text()="Back"]', $modal)->click();
    $assert_session->assertWaitOnAjaxRequest();

    if ($new_entity) {
      $assert_session->fieldExists('entity_id', $modal)->setValue(sprintf('%s (%s)', $new_entity->label(), $new_entity->id()));
    }

    $assert_session->elementExists('css', 'button.js-button-next', $modal)->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertStringContainsString('Selected entity', $modal->getText());
    $this->assertNotEmpty($modal->findLink($new_entity ? $new_entity->label() : $current_entity->label()));

    if ($display_mode !== NULL) {
      $assert_session->selectExists('Display as')->selectOption($display_mode);
    }

    $assert_session->elementExists('css', 'button.button--primary', $modal)->press();
    $assert_session->assertWaitOnAjaxRequest();
  }

  /**
   * Returns the markup for an embedded entity with a non-inline view mode.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $view_mode
   *   The view mode.
   * @param string $uuid
   *   The entity UUID.
   * @param string $label
   *   The entity label.
   *
   * @return string
   *   The embed markup as created by the JS plugin.
   */
  protected function getBlockEmbedString(string $entity_type, string $view_mode, string $uuid, string $label): string {
    return sprintf(
      '<p data-display-as="%s" data-oembed="https://oembed.ec.europa.eu?url=https%%3A//data.ec.europa.eu/ewp/%s/%s%%3Fview_mode%%3D%s"><a href="https://data.ec.europa.eu/ewp/%s/%s">%s</a></p>',
      $view_mode,
      $entity_type,
      $uuid,
      $view_mode,
      $entity_type,
      $uuid,
      $label
    );
  }

  /**
   * Returns the markup for an embedded entity with an inline view mode.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $view_mode
   *   The view mode.
   * @param string $uuid
   *   The entity UUID.
   * @param string $label
   *   The entity label.
   *
   * @return string
   *   The embed markup as created by the JS plugin.
   */
  protected function getInlineEmbedString(string $entity_type, string $view_mode, string $uuid, string $label): string {
    return sprintf(
      '<a href="https://data.ec.europa.eu/ewp/%s/%s" data-display-as="%s" data-oembed="https://oembed.ec.europa.eu?url=https%%3A//data.ec.europa.eu/ewp/%s/%s%%3Fview_mode%%3D%s">%s</a>',
      $entity_type,
      $uuid,
      $view_mode,
      $entity_type,
      $uuid,
      $view_mode,
      $label
    );
  }

}
