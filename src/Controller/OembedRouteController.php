<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Oembed route controller.
 */
class OembedRouteController {

  /**
   * Returns the oEmbed json object related to the request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getOembedJson(): JsonResponse {
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
  public function checkAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowed();
  }

}
