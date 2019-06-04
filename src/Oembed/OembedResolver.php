<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Oembed;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * Resolve the request into a valid oEmbed json response.
   *
   * @param \Drupal\Core\Url $url
   *   The requested url.
   *
   * @return array
   *   The json array.
   */
  public function resolve(Url $url) {
    $uri_parts = $this->parseUrl($url->getUri());

    // Add required parameters to response.
    $result_json = [
      'version' => '1.0',
    ];

    // Load the requested entity and get any additional parameter from the url.
    $entity = $this->getMediaFromPath($uri_parts['path']);
    if (!$entity) {
      throw new \Exception('Requested media entity was not found.');
    }

    $result_json += $this->mediaToJson($entity, $uri_parts);
    return $result_json;
  }

  /**
   * Retrieves the media associated to the received path.
   *
   * @param string $path
   *   The received path.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The requested media entity or NULL if not found.
   */
  public function getMediaFromPath(string $path) {
    $path = trim($path, '/');
    $parameters = explode('/', $path);
    if (empty($parameters) || count($parameters) < 2) {
      return NULL;
    }
    $media = $this->entityRepository->loadEntityByUuid($parameters[0], $parameters[1]);

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
  public function mediaToJson(MediaInterface $media, array $uri_parts) {
    $source = $media->getSource();
    $source_field_value = $source->getSourceFieldValue($media);
    if (!$source_field_value) {
      throw new \Exception('The media entity has no source field value');
    }

    $view_mode = $uri_parts['view_mode'] ?? NULL;

    if ($view_mode) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
      $view_display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.' . $media->bundle() . "." . $view_mode);
      if (!$view_display) {
        throw new \Exception('The requested view mode does not exist.');
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
    throw new \Exception('A non-supported media type has been requested.');
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
  public function processRemoteVideoMedia(MediaInterface $media, array $uri_parts) {
    $view_mode = $uri_parts['view_mode'] ?? NULL;

    if (!$view_mode) {
      $view_mode = 'default';
    }

    $source = $media->getSource();
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    $value = $media->get($source->getSourceFieldDefinition($media_type)->getName());
    $media_render_array = $this->entityTypeManager->getViewBuilder('media')->viewField($value, $view_mode);
    $rendered_media = $this->renderer->renderRoot($media_render_array);
    // @todo The Oembed formatter from core renders the iframe using a relative
    // URL. We need to find a way to get around this so the iframe URL is
    // absolute.
    return [
      'type' => 'video',
      'html' => (string) $rendered_media,
      'width' => $source->getMetadata($media, 'width'),
      'height' => $source->getMetadata($media, 'height'),
      'lang' => $media->language()->getId(),
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
   */
  public function processImageMedia(MediaInterface $media, array $uri_parts) {
    $view_mode = $uri_parts['view_mode'] ?? NULL;

    $source = $media->getSource();
    $source_field_value = $source->getSourceFieldValue($media);

    /** @var \Drupal\file\Entity\File $image */
    $image = $this->entityTypeManager->getStorage('file')->load($source_field_value);

    // If no view mode is requested, we return information about the original
    // image.
    if (!$view_mode) {
      return [
        'type' => 'photo',
        'url' => file_create_url($image->getFileUri()),
        'width' => $source->getMetadata($media, 'width'),
        'height' => $source->getMetadata($media, 'height'),
        'lang' => $media->language()->getId(),
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
      throw new \Exception('The media source field is not configured to show on this view mode.');
    }

    if ($component['type'] === 'image' && $component['settings']['image_style'] === "") {
      // If the image is rendered without an image style.
      return [
        'type' => 'photo',
        'url' => file_create_url($image->getFileUri()),
        'width' => $source->getMetadata($media, 'width'),
        'height' => $source->getMetadata($media, 'height'),
        'lang' => $media->language()->getId(),
      ];
    }

    if ($component['type'] === 'image' && $component['settings']['image_style'] !== "") {
      // If the image is rendered using an image style.
      $image_style = $this->entityTypeManager->getStorage('image_style')->load($component['settings']['image_style']);
      if (!$image_style instanceof ImageStyleInterface) {
        throw new \Exception('The image style the formatter is using does not exist.');
      }

      $url = $image_style->buildUrl($image->getFileUri());
      list($width, $height) = getimagesize($url);

      return [
        'type' => 'photo',
        'url' => $url,
        'width' => $width,
        'height' => $height,
        'lang' => $media->language()->getId(),
      ];
    }

    if ($component['type'] === 'responsive_image') {
      // If the image is rendered using a responsive image style.
      $responsive_image_style = $this->entityTypeManager->getStorage('responsive_image_style')->load($component['settings']['responsive_image_style']);
      if (!$responsive_image_style instanceof ResponsiveImageStyleInterface) {
        throw new \Exception('The responsive image style the formatter is using does not exist.');
      }

      // For calculating width and height we rely on the fallback image style
      // of the responsive image style.
      /** @var \Drupal\image\ImageStyleInterface $image_style */
      $image_style = $this->entityTypeManager->getStorage('image_style')->load($responsive_image_style->getFallbackImageStyle());
      $url = $image_style->buildUrl($image->getFileUri());
      list($width, $height) = getimagesize($url);
      // Then we render the field to get the <picture> tag.
      $responsive_image_render_array = $this->entityTypeManager->getViewBuilder('media')->viewField($media->{$source_field_definition->getName()}, $view_mode);
      $rendered_responsive_image = $this->renderer->renderRoot($responsive_image_render_array);

      return [
        'type' => 'rich',
        'html' => (string) $rendered_responsive_image,
        'width' => $width,
        'height' => $height,
        'lang' => $media->language()->getId(),
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
  public function processFileMedia(MediaInterface $media, array $uri_parts) {
    $source = $media->getSource();
    $source_field_value = $source->getSourceFieldValue($media);
    // For the moment we do not really render the File media. We simply return
    // information about the file and the link to it.
    $file = $this->entityTypeManager->getStorage('file')->load($source_field_value);
    if (!$file instanceof FileInterface) {
      throw new \Exception('The source file is missing.');
    }

    $json = [
      'type' => 'link',
      'name' => $media->getName(),
      'download' => file_create_url($file->getFileUri()),
      'size' => $file->getSize(),
      'mime' => $file->getMimeType(),
      'lang' => $media->language()->getId(),
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
  public function parseUrl(string $url) {
    global $base_url;
    $global_url_parts = parse_url($base_url);
    $target_url_parts = parse_url($url);
    $url_parts = [
      'path' => trim(str_replace($global_url_parts['path'], '', $target_url_parts['path']), '/'),
    ];
    if (!empty($target_url_parts['query'])) {
      parse_str($target_url_parts['query'], $query_parameters);
      $url_parts += $query_parameters;
    }

    return $url_parts;
  }

}
