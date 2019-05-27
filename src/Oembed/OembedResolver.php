<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Oembed;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    // If there is no path then we can't find anything to process.
    if (!isset($uri_parts['path'])) {
      throw new BadRequestHttpException('Requested url has no path.');
    }

    // Add required parameters to response.
    $result_json = [
      'version' => '1.0',
    ];
    // Load the requested entity and get any additional parameter from the url.
    $entity = $this->getEntityFromPath($uri_parts['path']);
    if (!$entity) {
      throw new NotFoundHttpException('Requested entity was not found.');
    }
    $view_mode = NULL;
    if (isset($uri_parts['view_mode'])) {
      $view_mode = $uri_parts['view_mode'];
    }

    // Process the request into the json array.
    switch ($entity->getEntityTypeId()) {
      case 'media':
        $result_json += $this->mediaToJson($entity, $view_mode);

    }
    return $result_json;
  }

  /**
   * Retrieves the entity associated to the received path.
   *
   * @param string $path
   *   The received path.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The requested entity or NULL if not found.
   */
  protected function getEntityFromPath(string $path) {
    $path = trim($path, '/');
    $parameters = explode('/', $path);
    if (empty($parameters) || count($parameters) < 2) {
      return NULL;
    }
    return $this->entityRepository->loadEntityByUuid($parameters[0], $parameters[1]);
  }

  /**
   * Formats a media entity into an oEmbed json.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media to be formatted into an oEmbed json.
   * @param string|null $view_mode
   *   The view mode to be used.
   *
   * @return array
   *   A properly formatted json array.
   */
  public function mediaToJson(MediaInterface $media, $view_mode = NULL) {
    $source = $media->getSource();
    switch ($source->getPluginId()) {
      case 'image':
        return $this->processImageMedia($media, $view_mode);

      case 'oembed:video':
        $json_array = $this->addRenderedMedia($media, $view_mode);
        if (!empty($json_array)) {
          $json_array += [
            'type' => 'video',
          ];
        }
        return $json_array;

      case 'file':
        $json_array = $this->addRenderedMedia($media, $view_mode);
        if (!empty($json_array)) {
          $json_array += [
            'type' => 'rich',
          ];
        }
        return $json_array;
    }
    return [];
  }

  /**
   * Adds rendered media to a Json array.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media to be formatted into an oEmbed json.
   * @param string|null $view_mode
   *   The image style or view mode to be used.
   *
   * @return array
   *   A properly formatted json array.
   */
  public function addRenderedMedia(MediaInterface $media, $view_mode) {
    if (!$view_mode) {
      $view_mode = 'default';
    }
    $source = $media->getSource();
    if ($source_field_value = $source->getSourceFieldValue($media)) {
      $media_render_array = $this->entityTypeManager->getViewBuilder('media')->view($media, $view_mode);
      $rendered_media = $this->renderer->renderRoot($media_render_array);
      return [
        'html' => (string) $rendered_media,
        'width' => $source->getMetadata($media, 'width'),
        'height' => $source->getMetadata($media, 'height'),
      ];
    }
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
      'path' => str_replace($global_url_parts['path'], '', $target_url_parts['path']),
    ];
    if (!empty($target_url_parts['query'])) {
      parse_str($target_url_parts['query'], $query_parameters);
      $url_parts += $query_parameters;
    }

    return $url_parts;
  }

  /**
   * Processes an Image media into a oEmbed json array.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media to be processed.
   * @param string $view_mode
   *   The view mode to use, if any.
   *
   * @return array
   *   An oEmbed json array.
   */
  public function processImageMedia(MediaInterface $media, $view_mode = NULL) {
    $source = $media->getSource();
    if ($source_field_value = $source->getSourceFieldValue($media)) {
      /** @var \Drupal\file\Entity\File $image */
      $image = $this->entityTypeManager->getStorage('file')->load($source_field_value);
      if ($view_mode) {
        /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_mode_entity */
        $view_display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.' . $media->bundle() . "." . $view_mode);
        $displayed_fields = $view_display->get('content');
        $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
        $source_field_definition = $source->getSourceFieldDefinition($media_type);
        if (isset($displayed_fields[$source_field_definition->getName()])) {
          $field_settings = $displayed_fields[$source_field_definition->getName()]['settings'];
          if (isset($field_settings['image_style'])) {
            /** @var \Drupal\image\ImageStyleInterface $image_style */
            $image_style = $this->entityTypeManager->getStorage('image_style')->load($field_settings['image_style']);
            if ($image_style) {
              $url = $image_style->buildUrl($image->getFileUri());
            }
            else {
              $url = file_create_url($image->getFileUri());
            }
            list($width, $height) = getimagesize($url);
            return [
              'type' => 'photo',
              'url' => $url,
              'width' => $width,
              'height' => $height,
            ];

          }
          if (isset($field_settings['responsive_image_style'])) {
            /** @var \Drupal\responsive_image\ResponsiveImageStyleInterface $responsive_image_style */
            $responsive_image_style = $this->entityTypeManager->getStorage('responsive_image_style')->load($field_settings['responsive_image_style']);
            /** @var \Drupal\image\ImageStyleInterface $image_style */
            $image_style = $this->entityTypeManager->getStorage('image_style')->load($responsive_image_style->getFallbackImageStyle());
            if ($image_style) {
              $url = $image_style->buildUrl($image->getFileUri());
            }
            else {
              $url = file_create_url($image->getFileUri());
            }
            list($width, $height) = getimagesize($url);
            $responsive_image_render_array = $this->entityTypeManager->getViewBuilder('media')->viewField($media->{$source_field_definition->getName()}, $view_mode);
            $rendered_responsive_image = $this->renderer->renderRoot($responsive_image_render_array);
            return [
              'type' => 'rich',
              'html' => (string) $rendered_responsive_image  ,
              'width' => $width,
              'height' => $height,
            ];

          }
        }

        // ERROR: Image is not displayed or formatter is not recognized.
        return [];

      }
      return [
        'type' => 'photo',
        'url' => file_create_url($image->getFileUri()),
        'width' => $source->getMetadata($media, 'width'),
        'height' => $source->getMetadata($media, 'height'),
      ];

    }
    // ERROR: Media has no image.
    return [];
  }

}
