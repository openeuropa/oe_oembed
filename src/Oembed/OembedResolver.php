<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Oembed;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\responsive_image\ResponsiveImageStyleInterface;

/**
 * Resolves incoming requests into a properly formated oEmbed json array.
 */
class OembedResolver implements OembedResolverInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the oEmbed resolver.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity repository.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, Renderer $renderer, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityRepository = $entity_repository;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(Url $url): OembedResolverResult {
    // Load the requested entity and get any additional parameter from the url.
    $media = $this->getMediaFromUrl($url);
    $cache = $this->getDefaultCacheDependency();
    if (!$media) {
      $exception = new OembedCacheableException('The requested media entity was not found.');
      $exception->addCacheableDependency($cache);
      throw $exception;
    }

    $payload = [
      'version' => '1.0',
    ];

    $payload += $this->mediaToJson($media, (array) $url->getOption('query'));
    if (isset($payload['cache'])) {
      $cache = $cache->merge($payload['cache']);
      unset($payload['cache']);
    }
    $result = new OembedResolverResult($url, $media, $payload);
    $result->addCacheableDependency($media);
    $result->addCacheableDependency($cache);

    return $result;
  }

  /**
   * Returns the UUID found in a given URL string.
   *
   * @param string $url
   *   The source URL string.
   *
   * @return string|null
   *   The UUID or null if not found.
   */
  public static function uuidFromUrl(string $url): ?string {
    $regex = '/' . Uuid::VALID_PATTERN . '/';
    preg_match($regex, $url, $matches);
    if (!$matches) {
      return NULL;
    }

    return $matches[0];
  }

  /**
   * Return the default cache dependency to include in al results.
   *
   * In preparing the oEmbed information for all/any media entities, there are
   * a few cache metadata bits that we need to include in all requests. This is
   * to, for example, prevent caching a request to one media entity with a
   * view mode and then keep returning that information even after that view
   * mode has been deleted.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cache metadata.
   */
  protected function getDefaultCacheDependency() {
    $cache = new CacheableMetadata();

    $cache->addCacheTags(['media_list']);
    $cache->addCacheContexts(['url']);

    // View mode list cache tag. We need this in case a new view mode is added.
    $cache->addCacheTags(['config:entity_view_mode']);

    // All the media view mode cache tags.
    $view_modes = $this->entityTypeManager->getStorage('entity_view_mode')->loadByProperties(['targetEntityType' => 'media']);
    foreach ($view_modes as $view_mode) {
      $cache->addCacheableDependency($view_mode);
    }

    // Entity view display config list cache tag. We need this in case a new
    // view display is added.
    $cache->addCacheTags(['config:entity_view_display']);

    // All the media entity view display cache tags.
    $view_modes = $this->entityTypeManager->getStorage('entity_view_display')->loadByProperties(['targetEntityType' => 'media']);
    foreach ($view_modes as $view_mode) {
      $cache->addCacheableDependency($view_mode);
    }

    return $cache;
  }

  /**
   * Retrieves the entity associated to the received path.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to resolve.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The requested media entity or NULL if not found.
   */
  protected function getMediaFromUrl(Url $url): ?MediaInterface {
    $uuid = static::uuidFromUrl($url->getUri());
    if (!$uuid) {
      return NULL;
    }

    $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
    return $media instanceof MediaInterface ? $media : NULL;
  }

  /**
   * Formats a media entity into an oEmbed json.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media to be formatted into an oEmbed json.
   * @param array $uri_parts
   *   The URI parts.
   *
   * @return array
   *   A properly formatted json array.
   */
  public function mediaToJson(MediaInterface $media, array $uri_parts): array {
    $source = $media->getSource();
    $source_field_value = $source->getSourceFieldValue($media);
    if (!$source_field_value) {
      $exception = new OembedCacheableException('The media entity has no source field value.');
      $exception->addCacheableDependency($this->getDefaultCacheDependency());
      throw $exception;
    }

    $view_mode = $uri_parts['view_mode'] ?? NULL;

    if ($view_mode) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
      $view_display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.' . $media->bundle() . "." . $view_mode);
      if (!$view_display) {
        $exception = new OembedCacheableException('The requested entity view display does not exist.');
        $exception->addCacheableDependency($this->getDefaultCacheDependency());
        throw $exception;
      }
    }

    switch ($source->getPluginId()) {
      case 'image':
        return $this->processImageMedia($media, $uri_parts);

      case 'oembed:video':
        return $this->processRemoteVideoMedia($media, $uri_parts);

      case 'file':
        return $this->processFileMedia($media, $uri_parts);
    }

    // @todo create a plugin system that can provide data for other source
    // plugin types.
    $exception = new OembedCacheableException('A non-supported media type has been requested.');
    $exception->addCacheableDependency($this->getDefaultCacheDependency());
    throw $exception;
  }

  /**
   * Processes a generic.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media to be formatted into an oEmbed json.
   * @param array $uri_parts
   *   The URI parts..
   *
   * @return array
   *   A properly formatted json array.
   */
  public function processRemoteVideoMedia(MediaInterface $media, array $uri_parts): array {
    $view_mode = $uri_parts['view_mode'] ?? NULL;
    $cache = $this->getDefaultCacheDependency();

    if (!$view_mode) {
      $view_mode = 'default';
    }

    $source = $media->getSource();
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
    $view_display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.' . $media->bundle() . "." . $view_mode);

    // Check if the view mode is configured to show the source field.
    $source_field_definition = $source->getSourceFieldDefinition($media_type);
    $component = $view_display->getComponent($source_field_definition->getName());
    if (!$component) {
      $exception = new OembedCacheableException('The media source field is not configured to show on this view mode.');
      $exception->addCacheableDependency($cache);
      throw $exception;
    }

    $value = $media->get($source->getSourceFieldDefinition($media_type)->getName());
    // We need to build the media render array inside its own render context
    // to prevent render cache metadata leakage.
    // @see EarlyRenderingControllerWrapperSubscriber.
    $media_render_array = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($value, $view_mode) {
      return $this->entityTypeManager->getViewBuilder('media')->viewField($value, $view_mode);
    });

    $rendered_media = $this->renderer->renderRoot($media_render_array);
    // @todo The Oembed formatter from core renders the iframe using a relative
    // URL. We need to find a way to get around this so the iframe URL is
    // absolute.
    return [
      'type' => 'video',
      'html' => (string) $rendered_media,
      'width' => $component['settings']['max_width'] ?? $source->getMetadata($media, 'width'),
      'height' => $component['settings']['max_height'] ?? $source->getMetadata($media, 'height'),
      'lang' => $media->language()->getId(),
      'cache' => $cache,
    ];
  }

  /**
   * Processes an Image media into a oEmbed json array.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media to be processed.
   * @param array $uri_parts
   *   The URI parts.
   *
   * @return array
   *   An oEmbed json array.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function processImageMedia(MediaInterface $media, array $uri_parts): array {
    $view_mode = $uri_parts['view_mode'] ?? NULL;
    $cache = $this->getDefaultCacheDependency();

    $source = $media->getSource();
    $source_field_value = $source->getSourceFieldValue($media);

    /** @var \Drupal\file\Entity\File $image */
    $image = $this->entityTypeManager->getStorage('file')->load($source_field_value);
    if (!$image instanceof FileInterface) {
      $exception = new OembedCacheableException('The source image is missing.');
      $exception->addCacheableDependency($cache);
      throw $exception;
    }

    $cache->addCacheableDependency($image);

    // If no view mode is requested, we return information about the original
    // image.
    if (!$view_mode) {
      return [
        'type' => 'photo',
        'url' => file_create_url($image->getFileUri()),
        'width' => $source->getMetadata($media, 'width'),
        'height' => $source->getMetadata($media, 'height'),
        'lang' => $media->language()->getId(),
        'cache' => $cache,
      ];
    }

    // Otherwise, we inspect the view mode for information about how the image.
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
    $view_display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.' . $media->bundle() . "." . $view_mode);

    // Check if the view mode is configured to show the source field.
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    $source_field_definition = $source->getSourceFieldDefinition($media_type);
    $component = $view_display->getComponent($source_field_definition->getName());
    if (!$component) {
      $exception = new OembedCacheableException('The media source field is not configured to show on this view mode.');
      $exception->addCacheableDependency($cache);
      throw $exception;
    }

    if ($component['type'] === 'image' && $component['settings']['image_style'] === "") {
      // If the image is rendered without an image style.
      return [
        'type' => 'photo',
        'url' => file_create_url($image->getFileUri()),
        'width' => $source->getMetadata($media, 'width'),
        'height' => $source->getMetadata($media, 'height'),
        'lang' => $media->language()->getId(),
        'cache' => $cache,
      ];
    }

    if ($component['type'] === 'image' && $component['settings']['image_style'] !== "") {
      // If the image is rendered using an image style.
      $image_style = $this->entityTypeManager->getStorage('image_style')->load($component['settings']['image_style']);
      if (!$image_style instanceof ImageStyleInterface) {
        $exception = new OembedCacheableException('The image style the formatter is using does not exist.');
        $exception->addCacheableDependency($cache);
        throw $exception;
      }

      $cache->addCacheableDependency($image_style);

      $uri = $image_style->buildUri($image->getFileUri());
      if (!file_exists($uri)) {
        $image_style->createDerivative($image->getFileUri(), $uri);
      }
      list($width, $height) = getimagesize($uri);

      return [
        'type' => 'photo',
        'url' => $image_style->buildUrl($image->getFileUri()),
        'width' => $width,
        'height' => $height,
        'lang' => $media->language()->getId(),
        'cache' => $cache,
      ];
    }

    if ($component['type'] === 'responsive_image') {
      // If the image is rendered using a responsive image style.
      $responsive_image_style = $this->entityTypeManager->getStorage('responsive_image_style')->load($component['settings']['responsive_image_style']);
      if (!$responsive_image_style instanceof ResponsiveImageStyleInterface) {
        $exception = new OembedCacheableException('The responsive image style the formatter is using does not exist.');
        $exception->addCacheableDependency($cache);
        throw $exception;
      }

      $cache->addCacheableDependency($responsive_image_style);

      // For calculating width and height we rely on the fallback image style
      // of the responsive image style.
      /** @var \Drupal\image\ImageStyleInterface $image_style */
      $image_style = $this->entityTypeManager->getStorage('image_style')->load($responsive_image_style->getFallbackImageStyle());
      $cache->addCacheableDependency($image_style);
      $uri = $image_style->buildUri($image->getFileUri());
      if (!file_exists($uri)) {
        $image_style->createDerivative($image->getFileUri(), $uri);
      }
      list($width, $height) = getimagesize($uri);
      // Then we render the field to get the <picture> tag.
      $responsive_image_render_array = $this->entityTypeManager->getViewBuilder('media')->viewField($media->{$source_field_definition->getName()}, $view_mode);
      $rendered_responsive_image = $this->renderer->renderRoot($responsive_image_render_array);

      return [
        'type' => 'rich',
        'html' => (string) $rendered_responsive_image,
        'width' => $width,
        'height' => $height,
        'lang' => $media->language()->getId(),
        'cache' => $cache,
      ];
    }
  }

  /**
   * Processes the file media.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media to be formatted into an oEmbed json.
   * @param array $uri_parts
   *   The URI parts.
   *
   * @return array
   *   A properly formatted json array.
   */
  public function processFileMedia(MediaInterface $media, array $uri_parts): array {
    $cache = $this->getDefaultCacheDependency();
    $source = $media->getSource();
    $source_field_value = $source->getSourceFieldValue($media);
    // For the moment we do not really render the File media. We simply return
    // information about the file and the link to it.
    $file = $this->entityTypeManager->getStorage('file')->load($source_field_value);
    if (!$file instanceof FileInterface) {
      $exception = new OembedCacheableException('The source file is missing.');
      $exception->addCacheableDependency($cache);
      throw $exception;
    }

    $cache->addCacheableDependency($file);

    $json = [
      'type' => 'link',
      'name' => $media->getName(),
      'download' => file_create_url($file->getFileUri()),
      'size' => $file->getSize(),
      'mime' => $file->getMimeType(),
      'lang' => $media->language()->getId(),
      'cache' => $cache,
    ];

    return $json;
  }

  /**
   * Parses the received url.
   *
   * @param string $url
   *   The url to parse.
   *
   * @return array
   *   And array with the path and all the query values.
   */
  protected function parseUrl(string $url): array {
    global $base_url;
    $global_url_parts = parse_url($base_url);
    $target_url_parts = parse_url($url);
    $url_parts = [
      'path' => isset($global_url_parts['path']) ? str_replace($global_url_parts['path'], '', $target_url_parts['path']) : $target_url_parts['path'],
    ];
    if (!empty($target_url_parts['query'])) {
      parse_str($target_url_parts['query'], $query_parameters);
      $url_parts += $query_parameters;
    }

    return $url_parts;
  }

}
