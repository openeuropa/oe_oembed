<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Oembed route controlled.
 */
class OembedRouteController {

  /**
   * Returns the oEmbed json object related to the request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The access result.
   */
  public function getOembedJson() {
    return new JsonResponse([
      'status' => 'OK',
    ]);
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(AccountInterface $account) {
    return AccessResult::allowed();
  }

}
