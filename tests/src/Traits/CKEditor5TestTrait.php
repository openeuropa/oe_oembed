<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_oembed\Traits;

use Behat\Mink\Element\NodeElement;

/**
 * Contains methods to help interact with CKEditor 5.
 *
 * To be used in addition to \Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait.
 */
trait CKEditor5TestTrait {

  /**
   * Returns the CKEditor 5 editor element that contains the content.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The editor element.
   */
  protected function getEditor(): NodeElement {
    return $this->assertSession()->elementExists('css', '.ck-editor .ck-content');
  }

  /**
   * Places the cursor at the beginning or end of an element.
   *
   * @param string $selector
   *   A CSS selector for the element which contents should be selected.
   * @param bool $start
   *   True to position the cursor at the beginning, false for the end.
   */
  protected function placeCursorAtBoundaryOfElement(string $selector, bool $start = TRUE): void {
    $javascript = <<<JS
(function() {
  const el = document.querySelector(".ck-editor__main $selector");
  const range = document.createRange();
  range.selectNodeContents(el);
  range.collapse($start);
  const sel = window.getSelection();
  sel.removeAllRanges();
  sel.addRange(range);
})();
JS;
    $this->getSession()->evaluateScript($javascript);
  }

}
