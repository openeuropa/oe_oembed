<?php

declare(strict_types=1);

namespace Drupal\oe_oembed_server_test\Plugin\ServiceMock;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mocks the oEmbed providers request.
 *
 * @ServiceMock(
 *   id = "oembed_providers",
 *   label = @Translation("The oEmbed providers request mock."),
 *   weight = 0,
 * )
 */
class OembedProviders extends PluginBase implements ServiceMockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Creates a new instance of this class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ConfigFactoryInterface $configFactory, protected ModuleExtensionList $moduleExtensionList) {
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
      $container->get('config.factory'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return (string) $request->getUri() === $this->configFactory->get('media.settings')->get('oembed_providers_url');
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $contents = file_get_contents($this->moduleExtensionList->getPath('oe_oembed_server_test') . '/responses/providers.json');
    return new Response(200, [], $contents);
  }

}
