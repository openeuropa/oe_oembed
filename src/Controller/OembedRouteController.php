<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\oe_oembed\Oembed\OembedResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Oembed route controller.
 */
class OembedRouteController {

  /**
   * The oEmbed resolver.
   *
   * @var \Drupal\oe_oembed\Oembed\OembedResolver
   */
  protected $oembedResolver;

  /**
   * Constructs the route controller.
   *
   * @param \Drupal\oe_oembed\Oembed\OembedResolver $oembed_resolver
   *   The entity repository.
   */
  public function __construct(OembedResolver $oembed_resolver) {
    $this->oembedResolver = $oembed_resolver;
  }

  /**
   * Returns the oEmbed json object related to the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The received request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getOembedJson(Request $request): JsonResponse {
    $query_parameters = $request->query->all();
    if (!isset($query_parameters['url'])) {
      throw new BadRequestHttpException('Url parameter not found.');
    }
    $json_array = $this->oembedResolver->resolve(Url::fromUri($query_parameters['url']));
    return new JsonResponse($json_array);
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
