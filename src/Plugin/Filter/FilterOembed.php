<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\Renderer;
use Drupal\embed\DomHelperTrait;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\oe_oembed\OembedDataResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter to convert our oEmbed markup into the local rendered entities.
 *
 * @Filter(
 *   id = "oe_oembed_filter",
 *   title = @Translation("Embeds entities using the oEmbed format"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class FilterOembed extends FilterBase implements ContainerFactoryPluginInterface {

  use DomHelperTrait;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The oembed data resolver.
   *
   * @var \Drupal\oe_oembed\OembedDataResolver
   */
  protected $oembedDataResolver;

  /**
   * Constructs a new FilterOembed object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_oembed\OembedDataResolver $oembedDataResolver
   *   The oembed data resolver.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Renderer $renderer, EntityTypeManagerInterface $entityTypeManager, OembedDataResolver $oembedDataResolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->entityTypeManager = $entityTypeManager;
    $this->oembedDataResolver = $oembedDataResolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('oe_oembed.uuid_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (strpos($text, 'data-oembed') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//*[@data-oembed]') as $node) {
      $this->replaceOembedNode($node, $result);
    }

    $result->setProcessedText($this->serialize($dom));
    return $result;
  }

  /**
   * Replaces the default oEmbed markup with the meaningful rendered one.
   *
   * Since we are the owner of the oEmbed resolver and we are rendering local
   * entities, we will load and render the local entity with the selected
   * optional view mode rather than call the external resolver (which is in any
   * case internal).
   *
   * @param \DOMNode $node
   *   The DOM node element to replace.
   * @param \Drupal\filter\FilterProcessResult $result
   *   The processed result.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function replaceOembedNode(\DOMNode $node, FilterProcessResult $result): void {
    $oembed = $node->getAttribute('data-oembed');
    $uuid = $this->oembedDataResolver->resolveUuid($oembed);
    if (!$uuid) {
      return;
    }

    // If we reached this point, we will replace the node with something even
    // if the entity is not found or the user doesn't have access to it. We
    // don't want anything displayed in these cases so we essentially kill the
    // embedded tag.
    $output = '';

    // Extract the entity type.
    $entity_type = $this->oembedDataResolver->resolveEntityType($oembed);
    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type, FALSE);
    if (!$entity_type_definition instanceof ContentEntityTypeInterface) {
      $this->replaceNodeContent($node, $output);
      return;
    }

    $entity = $this->entityTypeManager->getStorage($entity_type)->loadByProperties(['uuid' => $uuid]);
    if (!$entity) {
      $this->replaceNodeContent($node, $output);
      return;
    }

    $entity = reset($entity);

    $view_mode = $this->oembedDataResolver->resolveViewMode($oembed);
    $build = $this->entityTypeManager->getViewBuilder($entity_type)->view($entity, $view_mode);
    $cache = CacheableMetadata::createFromRenderArray($build);
    $access = $entity->access('view', NULL, TRUE);
    $cache->addCacheableDependency($access);
    if ($access instanceof AccessResultAllowed) {
      $context = new RenderContext();
      $output = (string) $this->renderer->executeInRenderContext($context, function () use (&$build) {
        return $this->renderer->render($build);
      });

      if (!$context->isEmpty()) {
        $result->addCacheableDependency($context->pop());
      }
    }

    $result->addCacheableDependency($cache);

    $this->replaceNodeContent($node, (string) $output);
  }

  /**
   * Converts the body of a \DOMDocument back to an HTML snippet.
   *
   * Custom serialize method used to serialize the body part of a \DOMDocument
   * back to an (X)HTML. Used instead of Html::serialize because that method is
   * wrapping the snippet in cdata.
   *
   * @param \DOMDocument $document
   *   A \DOMDocument object to serialize, only the tags below the first <body>
   *   node will be converted.
   *
   * @return string
   *   A valid (X)HTML snippet, as a string.
   *
   * @see Html::serialize()
   */
  private function serialize(\DOMDocument $document) {
    $body_node = $document->getElementsByTagName('body')->item(0);
    $html = '';

    if ($body_node !== NULL) {
      foreach ($body_node->childNodes as $node) {
        $html .= $document->saveHTML($node);
      }
    }
    return $html;
  }

}
