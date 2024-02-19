<?php

declare(strict_types=1);

namespace Drupal\oe_oembed_server\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\oe_oembed_server\Oembed\OembedCacheableException;
use Drupal\oe_oembed_server\Oembed\OembedResolver;
use Drupal\oe_oembed_server\Oembed\OembedResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Oembed route controller.
 */
class OembedRouteController implements ContainerInjectionInterface {

  /**
   * The oEmbed resolver.
   *
   * @var \Drupal\oe_oembed_server\Oembed\OembedResolverInterface
   */
  protected $oembedResolver;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * OembedRouteController constructor.
   *
   * @param \Drupal\oe_oembed_server\Oembed\OembedResolverInterface $oembedResolver
   *   The oEmbed resolver.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   */
  public function __construct(OembedResolverInterface $oembedResolver, Request $request, EntityRepositoryInterface $entityRepository) {
    $this->oembedResolver = $oembedResolver;
    $this->request = $request;
    $this->entityRepository = $entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_oembed_server.oembed_resolver'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity.repository')
    );
  }

  /**
   * Returns the oEmbed json object related to the request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getOembedJson(): JsonResponse {
    $url_string = $this->request->query->get('url');
    $parsed = UrlHelper::parse($url_string);
    $url = Url::fromUri($parsed['path'], ['query' => $parsed['query']]);
    try {
      $result = $this->oembedResolver->resolve($url);
    }
    catch (OembedCacheableException $exception) {
      $response = new CacheableJsonResponse($exception->getMessage(), 404);
      $response->addCacheableDependency($exception);
      return $response;
    }

    $response = new CacheableJsonResponse($result->getData());
    $response->addCacheableDependency($result);

    return $response;
  }

  /**
   * Access callback for the resolver.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(AccountInterface $account): AccessResultInterface {
    $url_string = $this->request->query->get('url');
    if (!$url_string) {
      return AccessResult::forbidden('There is no URL present in the oEmbed request.')->addCacheContexts(['url']);
    }

    if (!UrlHelper::isValid($url_string)) {
      return AccessResult::forbidden('The oEmbed resource URL is invalid.')->addCacheContexts(['url']);
    }

    $uuid = OembedResolver::uuidFromUrl($url_string);
    if (!$uuid) {
      return AccessResult::forbidden('The oEmbed resource URL does not contain a valid resource UUID.')->addCacheContexts(['url']);
    }

    $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
    if (!$media) {
      return AccessResult::forbidden('The requested oEmbed resource was not found.')->addCacheContexts(['url']);
    }

    $access = $media->access('view', $account, TRUE);
    $access->addCacheContexts(['url']);

    return $access;
  }

}
