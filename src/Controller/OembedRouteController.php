<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\oe_oembed\Oembed\OembedResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs the route controller.
   *
   * @param \Drupal\oe_oembed\Oembed\OembedResolver $oembed_resolver
   *   The entity repository.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(OembedResolver $oembed_resolver, RequestStack $request_stack) {
    $this->oembedResolver = $oembed_resolver;
    $this->requestStack = $request_stack;

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
    global $base_url;
    $query_parameters = $request->query->all();
    $url = str_replace('https://data.ec.europa.eu/ewp', $base_url, $query_parameters['url']);
    try {
      $json_array = $this->oembedResolver->resolve(Url::fromUri($url));
    }
    catch (\Exception $exception) {
      throw new NotFoundHttpException('Url could not be resolved.');
    }

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
    global $base_url;
    $request = $this->requestStack->getCurrentRequest();
    $query_parameters = $request->query->all();
    if (!isset($query_parameters['url'])) {
      throw new NotFoundHttpException('Url parameter not found.');
    }
    $url = str_replace('https://data.ec.europa.eu/ewp', $base_url, $query_parameters['url']);
    $uri_parts = $this->oembedResolver->parseUrl($url);

    // If there is no path then we can't find anything to process.
    if (empty($uri_parts['path'])) {
      throw new NotFoundHttpException('Requested url has no path.');
    }
    $entity = $this->oembedResolver->getEntityFromPath($uri_parts['path']);
    if (!$entity) {
      throw new NotFoundHttpException('Requested entity not found.');
    }
    return $entity->access('view', $account, TRUE);
  }

}
