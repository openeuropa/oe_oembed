<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_oembed\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\editor\EditorInterface;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Symfony\Component\Validator\ConstraintViolation;
use WebDriver\Exception\ElementClickIntercepted;

/**
 * Tests CKEditor integration.
 */
class CKEditor5IntegrationTest extends WebDriverTestBase {

  use CKEditor5TestTrait;

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
        'value' => $this->getBlockEmbedString('default', $embeddable->uuid(), $embeddable->label()),
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
      'value' => $this->getInlineEmbedString('default', $embeddable->uuid(), $embeddable->label()),
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
  public function testEmbedSingleButton(): void {
    // Enable the node embed button.
    $this->editor->setSettings(array_merge_recursive($this->editor->getSettings(), [
      'toolbar' => [
        'items' => [
          'node',
        ],
      ],
    ]))->save();
    $this->validateEditorFormatPair();

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
        'value' => '<p>First paragraph</p><p><strong>Some text</strong></p><p>Last paragraph</p>',
        'format' => 'test_format',
      ],
    ]);
    $host->save();

    $this->drupalLogin($this->user);

    $this->drupalGet($host->toUrl('edit-form'));
    $this->waitForEditor();
    // Select the <strong> tag.
    $this->selectTextInsideElement('strong');
    $this->pressEditorButton('Node');
    $assert_session = $this->assertSession();
    $modal = $assert_session->waitForElement('css', '.oe-oembed-entities-select-dialog');
    $assert_session->fieldExists('entity_id', $modal)->setValue(sprintf('%s (%s)', $embeddable->label(), $embeddable->id()));
    $assert_session->elementExists('css', 'button.js-button-next', $modal)->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertStringContainsString('Selected entity', $modal->getText());
    $this->assertNotEmpty($modal->findLink($embeddable->label()));
    $assert_session->elementExists('css', 'button.button--primary', $modal)->press();
    $assert_session->assertWaitOnAjaxRequest();
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('p.ck-oe-oembed.ck-widget > span > a[href="https://data.ec.europa.eu/ewp/node/%s"]', $embeddable->uuid()), $editor);
    $this->assertEquals($embeddable->label(), $link->getHtml());
    // The strong tag has been replaced by the embedded entity markup.
    $this->assertEquals('<p>First paragraph</p>' . $this->getBlockEmbedString('embed', $embeddable->uuid(), $embeddable->label()) . '<p>Last paragraph</p>', $this->getEditorDataAsHtmlString());

    // Create a new node to embed.
    $another_embeddable = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'Different page to embed',
    ]);
    $another_embeddable->save();

    // Edit the previous embedded node and replace it with the new one.
    $assert_session->elementExists('css', 'p.ck-oe-oembed.ck-widget', $editor)->click();
    $this->getBalloonButton('Edit')->click();
    $modal = $assert_session->waitForElement('css', '.oe-oembed-entities-select-dialog');
    $this->assertStringContainsString('Selected entity', $modal->getText());
    $this->assertNotEmpty($modal->findLink($embeddable->label()));
    $assert_session->elementExists('xpath', '//button[text()="Back"]', $modal)->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldExists('entity_id', $modal)->setValue(sprintf('%s (%s)', $another_embeddable->label(), $another_embeddable->id()));
    $assert_session->elementExists('css', 'button.js-button-next', $modal)->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertStringContainsString('Selected entity', $modal->getText());
    $this->assertNotEmpty($modal->findLink($another_embeddable->label()));
    $assert_session->elementExists('css', 'button.button--primary', $modal)->press();
    $assert_session->assertWaitOnAjaxRequest();
    $editor = $assert_session->elementExists('css', '.ck-editor .ck-content');
    $link = $assert_session->elementExists('css', sprintf('p.ck-oe-oembed.ck-widget > span > a[href="https://data.ec.europa.eu/ewp/node/%s"]', $another_embeddable->uuid()), $editor);
    $this->assertEquals($another_embeddable->label(), $link->getHtml());
    $this->assertEquals('<p>First paragraph</p>' . $this->getBlockEmbedString('embed', $another_embeddable->uuid(), $another_embeddable->label()) . '<p>Last paragraph</p>', $this->getEditorDataAsHtmlString());
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
   *
   * @param \Behat\Mink\Element\NodeElement $link
   *   The link to click.
   */
  protected function attemptLinkClick(NodeElement $link): void {
    try {
      $link->click();
    }
    catch (ElementClickIntercepted $e) {
      return;
    }
  }

  /**
   * Returns the markup for an embedded entity with a non-inline view mode.
   *
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
  protected function getBlockEmbedString(string $view_mode, string $uuid, string $label): string {
    return sprintf(
      '<p data-display-as="%s" data-oembed="https://oembed.ec.europa.eu?url=https%%3A//data.ec.europa.eu/ewp/node/%s%%3Fview_mode%%3D%s"><a href="https://data.ec.europa.eu/ewp/node/%s">%s</a></p>',
      $view_mode,
      $uuid,
      $view_mode,
      $uuid,
      $label
    );
  }

  /**
   * Returns the markup for an embedded entity with an inline view mode.
   *
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
  protected function getInlineEmbedString(string $view_mode, string $uuid, string $label): string {
    return sprintf(
      '<a href="https://data.ec.europa.eu/ewp/node/%s" data-display-as="%s" data-oembed="https://oembed.ec.europa.eu?url=https%%3A//data.ec.europa.eu/ewp/node/%s%%3Fview_mode%%3D%s">%s</a>',
      $uuid,
      $view_mode,
      $uuid,
      $view_mode,
      $label
    );
  }

}
