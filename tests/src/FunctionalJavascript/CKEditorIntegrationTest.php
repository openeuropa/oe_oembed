<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_oembed\FunctionalJavascript;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Tests CKEditor integration.
 */
class CKEditorIntegrationTest extends EmbedTestBase {

  /**
   * The test media button.
   *
   * @var \Drupal\embed\Entity\EmbedButton
   */
  protected $mediaButton;

  /**
   * The test node button.
   *
   * @var \Drupal\embed\Entity\EmbedButton
   */
  protected $nodeButton;


  /**
   * The test administrative user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->mediaButton = $this->container->get('entity_type.manager')
      ->getStorage('embed_button')
      ->load('media');

    $this->nodeButton = $this->container->get('entity_type.manager')
      ->getStorage('embed_button')
      ->load('node');

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'access embed_media_entity_browser_test entity browser pages',
      'administer filters',
      'administer display modes',
      'administer embed buttons',
      'administer site configuration',
      'administer display modes',
      'administer content types',
      'administer node display',
      'access content',
      'create page content',
      'edit own page content',
      'use text format html',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests integration with CKEditor.
   *
   * We test that the we can configure a text format to use our widget and
   * that we can embed entities in the WYSIWYG.
   */
  public function testIntegration(): void {
    // Verify that the Embed button shows up and results in an
    // operational entity embedding experience in the text editor.
    $this->drupalGet('/node/add/page');
    $this->assignNameToCkeditorIframe();

    $this->getSession()->switchToIFrame('ckeditor');
    $this->assertSession()->pageTextNotContains('My image media');
    $this->assertSession()->pageTextNotContains('Digital Single Market: cheaper calls to other EU countries as of 15 May');

    // Embed the Image media.
    $this->getSession()->switchToIFrame();
    $this->assertSession()->elementExists('css', 'a.cke_button__' . $this->mediaButton->id())->click();
    $this->assertSession()->waitForId('drupal-modal');
    $this->assertSession()->fieldExists('entity_id')->setValue('My image media (1)');
    $this->assertSession()->elementExists('css', 'button.js-button-next')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->responseContains('Selected entity');
    $this->assertSession()->linkExists('My image media');
    $this->assertSession()->fieldExists('Display as')->selectOption('Image teaser');
    $this->assertSession()->elementExists('css', 'button.button--primary')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Embed the Remote video media.
    $this->getSession()->switchToIFrame('ckeditor');
    // Click on the next, empty paragraph so that pressing the embed button
    // embeds a new one and doesn't open for the existing (selected) one.
    $this->getSession()->getPage()->find('xpath', '//p[not(@*)]')->click();
    $this->getSession()->switchToIFrame();
    $this->assertSession()->elementExists('css', 'a.cke_button__' . $this->mediaButton->id())->click();
    $this->assertSession()->waitForId('drupal-modal');
    $this->assertSession()->fieldExists('entity_id')->setValue('Digital Single Market: cheaper calls to other EU countries as of 15 May (2)');
    $this->assertSession()->elementExists('css', 'button.js-button-next')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->responseContains('Selected entity');
    $this->assertSession()->linkExists('Digital Single Market: cheaper calls to other EU countries as of 15 May');
    $this->assertSession()->fieldNotExists('Display as');
    $this->assertSession()->elementExists('css', 'button.button--primary')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify that the embedded entity gets a preview inside the text editor.
    $this->getSession()->switchToIFrame('ckeditor');
    $this->assertSession()->pageTextContains('My image media');
    $this->assertSession()->pageTextContains('Digital Single Market: cheaper calls to other EU countries as of 15 May');
    $this->getSession()->switchToIFrame();
    $this->getSession()->getPage()->fillField('Title', 'Node with embedded media');
    $this->assertSession()->buttonExists('Save')->press();

    // Verify that the embedded media are found in the markup.
    $media = $this->container->get('entity_type.manager')
      ->getStorage('media')
      ->loadMultiple();

    $element = new FormattableMarkup('<p data-display-as="image_teaser" data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/@uuid%3Fview_mode%3Dimage_teaser"><a href="https://data.ec.europa.eu/ewp/media/@uuid">@title</a></p>', [
      '@uuid' => $media[1]->uuid(),
      '@title' => $media[1]->label(),
    ]);
    $this->assertStringContainsString($element->__toString(), $this->getSession()->getPage()->getHtml());

    $element = new FormattableMarkup('<p data-display-as="embed" data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/@uuid%3Fview_mode%3Dembed"><a href="https://data.ec.europa.eu/ewp/media/@uuid">@title</a></p>', [
      '@uuid' => $media[2]->uuid(),
      '@title' => $media[2]->label(),
    ]);
    $this->assertStringContainsString($element->__toString(), $this->getSession()->getPage()->getHtml());

    // Edit the node and change the media embed view mode.
    $node = $this->drupalGetNodeByTitle('Node with embedded media');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assignNameToCkeditorIframe();
    $this->getSession()->switchToIFrame('ckeditor');
    $this->getSession()->getPage()->find('xpath', "//p[@data-display-as='image_teaser']")->rightClick();
    $this->getSession()->switchToIFrame();
    $this->assignNameToCkeditorPanelIframe();
    $this->getSession()->switchToIFrame('panel');
    $this->getSession()->getPage()->clickLink('Edit display options');
    $this->assertSession()->waitForId('drupal-modal');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->linkExists('My image media');
    $select = $this->assertSession()->fieldExists('Display as');
    $this->assertEquals('image_teaser', $select->find('css', 'option[selected]')->getValue());
    $select->selectOption('Demo');
    $this->assertSession()->elementExists('css', 'button.button--primary')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->buttonExists('Save')->press();

    // Assert that the image media embed view mode was changed and the other
    // stayed the same.
    $element = new FormattableMarkup('<p data-display-as="demo" data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/@uuid%3Fview_mode%3Ddemo"><a href="https://data.ec.europa.eu/ewp/media/@uuid">@title</a></p>', [
      '@uuid' => $media[1]->uuid(),
      '@title' => $media[1]->label(),
    ]);
    $this->assertStringContainsString($element->__toString(), $this->getSession()->getPage()->getHtml());

    $element = new FormattableMarkup('<p data-display-as="embed" data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/media/@uuid%3Fview_mode%3Dembed"><a href="https://data.ec.europa.eu/ewp/media/@uuid">@title</a></p>', [
      '@uuid' => $media[2]->uuid(),
      '@title' => $media[2]->label(),
    ]);
    $this->assertStringContainsString($element->__toString(), $this->getSession()->getPage()->getHtml());

    // Now try embedding an existing node.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Embed node',
    ]);
    $node->save();

    $this->drupalGet('/node/add/page');
    $this->assignNameToCkeditorIframe();

    $this->getSession()->switchToIFrame('ckeditor');
    $this->assertSession()->pageTextNotContains('Embed node');

    // Embed the node.
    $this->getSession()->switchToIFrame();
    $this->assertSession()->elementExists('css', 'a.cke_button__' . $this->nodeButton->id())->click();
    $this->assertSession()->waitForId('drupal-modal');
    $this->assertSession()->fieldExists('entity_id')->setValue('Embed node (' . $node->id() . ')');
    $this->assertSession()->elementExists('css', 'button.js-button-next')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->responseContains('Selected entity');
    $this->assertSession()->linkExists('Embed node');
    $this->assertSession()->fieldNotExists('Display as');
    $this->assertSession()->elementExists('css', 'button.button--primary')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify that the embedded entity gets a preview inside the text editor.
    $this->getSession()->switchToIFrame('ckeditor');
    $this->assertSession()->pageTextContains('Embed node');

    // Save the page.
    $this->getSession()->switchToIFrame();
    $this->getSession()->getPage()->fillField('Title', 'Node with embedded node');
    $this->assertSession()->buttonExists('Save')->press();

    $element = new FormattableMarkup('<p data-display-as="embed" data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/node/@uuid%3Fview_mode%3Dembed"><a href="https://data.ec.europa.eu/ewp/node/@uuid">@title</a></p>', [
      '@uuid' => $node->uuid(),
      '@title' => $node->label(),
    ]);
    $this->assertStringContainsString($element->__toString(), $this->getSession()->getPage()->getHtml());

    // Embed again the node but this time enable a new view mode configured to
    // be embedded inline.
    $view_display = EntityViewDisplay::load('node.page.inline');
    $view_display->setThirdPartySetting('oe_oembed', 'inline', TRUE);
    $view_display->setThirdPartySetting('oe_oembed', 'embeddable', TRUE);
    $view_display->save();

    $this->drupalGet('/node/add/page');
    $this->assignNameToCkeditorIframe();

    $this->getSession()->switchToIFrame('ckeditor');
    $this->assertSession()->pageTextNotContains('Embed node');

    // Embed the node.
    $this->getSession()->switchToIFrame();
    $this->assertSession()->elementExists('css', 'a.cke_button__' . $this->nodeButton->id())->click();
    $this->assertSession()->waitForId('drupal-modal');
    $this->assertSession()->fieldExists('entity_id')->setValue('Embed node (' . $node->id() . ')');
    $this->assertSession()->elementExists('css', 'button.js-button-next')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->responseContains('Selected entity');
    $this->assertSession()->linkExists('Embed node');
    $this->assertSession()->fieldExists('Display as')->selectOption('Inline');
    $this->assertSession()->elementExists('css', 'button.button--primary')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Verify that the embedded entity gets a preview inside the text editor.
    $this->getSession()->switchToIFrame('ckeditor');
    $this->assertSession()->pageTextContains('Embed node');

    // Save the page.
    $this->getSession()->switchToIFrame();
    $this->getSession()->getPage()->fillField('Title', 'Node with embedded inline node');
    $this->assertSession()->buttonExists('Save')->press();

    $element = new FormattableMarkup('<a data-display-as="inline" data-oembed="https://oembed.ec.europa.eu?url=https%3A//data.ec.europa.eu/ewp/node/@uuid%3Fview_mode%3Dinline" href="https://data.ec.europa.eu/ewp/node/@uuid">@title</a>', [
      '@uuid' => $node->uuid(),
      '@title' => $node->label(),
    ]);
    $this->assertStringContainsString($element->__toString(), $this->getSession()->getPage()->getHtml());
  }

  /**
   * Tests the integration with entity browsers.
   */
  public function testEntityBrowserIntegration(): void {
    // Enable the entity browser integration.
    $this->drupalGet('admin/config/content/embed/button/manage/media');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Entity browser', 'embed_media_entity_browser_test');
    $page->pressButton('Save');
    $this->assertSession()->pageTextContains('The embed button Media has been updated.');

    $this->drupalGet('/node/add/page');
    $this->assignNameToCkeditorIframe();
    $this->getSession()->switchToIFrame('ckeditor');
    $this->assertSession()->pageTextNotContains('My image media');
    $this->assertSession()->pageTextNotContains('Digital Single Market: cheaper calls to other EU countries as of 15 May');

    // Embed the Image media.
    $this->getSession()->switchToIFrame();
    $this->assertSession()->elementExists('css', 'a.cke_button__' . $this->mediaButton->id())->click();
    $this->assertSession()->waitForId('drupal-modal');
    // Assert that the embed dialog URL contains in its POST values information
    // about the current page the embed is found on.
    $this->assertEquals([
      'current_route' => 'node.add',
      'current_route_parameters' => [
        'node_type' => 'page',
      ],
    ], \Drupal::state()->get('oe_oembed_test.embed_url.current_route_data'));

    $this->getSession()->switchToIFrame('entity_browser_iframe_embed_media_entity_browser_test');
    // Check the image checkbox.
    $this->getSession()->getPage()->checkField('entity_browser_select[media:1]');
    $this->assertSession()->buttonExists('Select entities')->click();
    $this->getSession()->switchToIFrame();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Selected entity');
    $this->assertSession()->linkExists('My image media');
    $this->assertSession()->fieldExists('Display as')->selectOption('Image teaser');
    // Press the "Embed" button in the modal actions.
    $this->assertSession()->elementExists('css', 'button.button--primary')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Embed the Remote video media.
    $this->getSession()->switchToIFrame();
    $this->assertSession()->elementExists('css', 'a.cke_button__' . $this->mediaButton->id())->click();
    $this->assertSession()->waitForId('drupal-modal');
    $this->getSession()->switchToIFrame('entity_browser_iframe_embed_media_entity_browser_test');
    // Check the video checkbox.
    $this->getSession()->getPage()->checkField('entity_browser_select[media:2]');
    $this->assertSession()->buttonExists('Select entities')->click();
    $this->getSession()->switchToIFrame();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Selected entity');
    $this->assertSession()->linkExists('Digital Single Market: cheaper calls to other EU countries as of 15 May');
    // Press the "Embed" button in the modal actions.
    $this->assertSession()->elementExists('css', 'button.button--primary')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify that the embedded entities get a preview inside the text editor.
    $this->getSession()->switchToIFrame('ckeditor');
    $this->assertSession()->pageTextContains('My image media');
    $this->assertSession()->pageTextContains('Digital Single Market: cheaper calls to other EU countries as of 15 May');
    $this->getSession()->switchToIFrame();
  }

  /**
   * Assigns a name to the CKEditor iframe, to allow use of ::switchToIFrame().
   *
   * @see \Behat\Mink\Session::switchToIFrame()
   */
  protected function assignNameToCkeditorIframe() {
    $javascript = <<<JS
(function(){
  document.getElementsByClassName('cke_wysiwyg_frame')[0].id = 'ckeditor';
})()
JS;
    $this->getSession()->evaluateScript($javascript);
  }

  /**
   * Assigns a name to the CKEditor context menu iframe.
   *
   * Note that this iframe doesn't appear until context menu appears.
   */
  protected function assignNameToCkeditorPanelIframe() {
    $javascript = <<<JS
(function(){
  document.getElementsByClassName('cke_panel_frame')[0].id = 'panel';
})()
JS;
    $this->getSession()->evaluateScript($javascript);
  }

}
