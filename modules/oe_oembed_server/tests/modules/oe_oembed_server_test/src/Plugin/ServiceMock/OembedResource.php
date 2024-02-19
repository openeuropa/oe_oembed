<?php

declare(strict_types=1);

namespace Drupal\oe_oembed_server_test\Plugin\ServiceMock;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mocks requests to oEmbed resources.
 *
 * @ServiceMock(
 *   id = "oembed_resource",
 *   label = @Translation("The single oEmbed resource request mock."),
 *   weight = 0,
 * )
 */
class OembedResource extends PluginBase implements ServiceMockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The list of allowed providers.
   *
   * @var array
   */
  protected const ALLOWED_PROVIDERS = [
    'youtube' => 'www.youtube.com',
  ];

  /**
   * Creates a new instance of this class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ModuleExtensionList $moduleExtensionList) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return in_array($request->getUri()->getHost(), self::ALLOWED_PROVIDERS);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $provider = array_search($request->getUri()->getHost(), self::ALLOWED_PROVIDERS);
    $resource_id = $this->getResourceId($request->getUri(), $provider);
    $path = sprintf('%s/responses/resources/%s/%s.json',
      $this->moduleExtensionList->getPath('oe_oembed_server_test'),
      $provider,
      $resource_id
    );

    $contents = file_exists($path) ? file_get_contents($path) : NULL;

    return new Response(200, ['Content-Type' => 'application/json'], $contents);
  }

  /**
   * Helper function for extracting the resource id from the oEmbed url.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The URI.
   * @param string $provider
   *   The provider.
   *
   * @return null|string
   *   The ID.
   */
  protected function getResourceId(UriInterface $uri, string $provider): ?string {
    $video_id = NULL;
    switch ($provider) {
      case 'youtube':
        // For example:
        // https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=z0NfI2NeDHI
        parse_str(parse_url($uri->__toString(), PHP_URL_QUERY), $parsed);
        parse_str(parse_url($parsed['url'] ?? '', PHP_URL_QUERY), $url);
        $video_id = $url['v'] ?? NULL;

        break;
    }

    return $video_id;
  }

}
