<?php

declare(strict_types = 1);

namespace Drupal\oe_oembed\Plugin\EmbedType;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\embed\EmbedType\EmbedTypeBase;
use Drupal\entity_browser\EntityBrowserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Embeds Drupal entities in a Drupal-agnostic way.
 *
 * @EmbedType(
 *   id = "oe_oembed_entities",
 *   label = @Translation("Drupal entities"),
 * )
 */
class OEmbedEntities extends EmbedTypeBase implements ContainerFactoryPluginInterface {

  use PluginDependencyTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Constructs a new instance of the class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entityTypeRepository
   *   The entity type repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Extension\ModuleExtensionList|null $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface|null $file_url_generator
   *   The file url generator.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeRepositoryInterface $entityTypeRepository,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    ModuleExtensionList $moduleExtensionList = NULL,
    FileUrlGeneratorInterface $file_url_generator = NULL
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeRepository = $entityTypeRepository;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->moduleExtensionList = $moduleExtensionList ?? \Drupal::service('extension.list.module');
    $this->fileUrlGenerator = $file_url_generator ?? \Drupal::service('file_url_generator');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_type.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('extension.list.module'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'entity_type' => '',
      'bundles' => [],
      'entity_browser' => '',
      'entity_browser_settings' => [
        'display_review' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $embed_button = $form_state->getTemporaryValue('embed_button');
    $entity_type_id = $this->getConfigurationValue('entity_type');

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $this->getEntityTypeOptions(),
      '#default_value' => $entity_type_id,
      '#description' => $this->t('The entity type this button will embed.'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$form_state->getFormObject(), 'updateTypeSettings'],
        'effect' => 'fade',
      ],
      '#empty_value' => '',
      '#disabled' => !$embed_button->isNew(),
    ];

    if ($entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $form['bundles'] = [
        '#type' => 'checkboxes',
        '#title' => $entity_type->getBundleLabel() ?: $this->t('Bundles'),
        '#options' => $this->getEntityBundleOptions($entity_type),
        '#default_value' => $this->getConfigurationValue('bundles'),
        '#description' => $this->t('If none are selected, all are allowed.'),
      ];
      $form['bundles']['#access'] = !empty($form['bundles']['#options']);

      /** @var \Drupal\entity_browser\EntityBrowserInterface[] $browsers */
      if ($this->entityTypeManager->hasDefinition('entity_browser') && ($browsers = $this->entityTypeManager->getStorage('entity_browser')->loadMultiple())) {
        // Filter out unsupported displays & return array of ids and labels.
        $browsers = array_map(
          function ($item) {
            /** @var \Drupal\entity_browser\EntityBrowserInterface $item */
            return $item->label();
          },
          // Filter out both modal and standalone forms as they don't work.
          array_filter($browsers, function (EntityBrowserInterface $browser) {
            return !in_array($browser->getDisplay()->getPluginId(), [
              'modal',
              'standalone',
            ], TRUE);
          })
        );
        $options = ['_none' => $this->t('None (autocomplete)')] + $browsers;
        $form['entity_browser'] = [
          '#type' => 'select',
          '#title' => $this->t('Entity browser'),
          '#description' => $this->t('Entity browser to be used to select entities to be embedded. Only compatible browsers will be available to be chosen.'),
          '#options' => $options,
          '#default_value' => $this->getConfigurationValue('entity_browser'),
        ];
        $form['entity_browser_settings'] = [
          '#type' => 'details',
          '#title' => $this->t('Entity browser settings'),
          '#open' => TRUE,
          '#states' => [
            'invisible' => [
              ':input[name="type_settings[entity_browser]"]' => ['value' => '_none'],
            ],
          ],
        ];
        $form['entity_browser_settings']['display_review'] = [
          '#type' => 'checkbox',
          '#title' => 'Display the entity after selection',
          '#default_value' => $this->getConfigurationValue('entity_browser_settings')['display_review'],
        ];
      }
      else {
        $form['entity_browser'] = [
          '#type' => 'value',
          '#value' => '',
        ];
      }
    }

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Filter down the bundles and allowed Entity Embed Display plugins.
    $bundles = $form_state->getValue('bundles');
    $form_state->setValue('bundles', array_keys(array_filter($bundles)));
    $entity_browser = $form_state->getValue('entity_browser') == '_none' ? '' : $form_state->getValue('entity_browser');
    $form_state->setValue('entity_browser', $entity_browser);
    $form_state->setValue('entity_browser_settings', $form_state->getValue('entity_browser_settings', []));

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Builds a list of entity type options.
   *
   * @return array
   *   An array of entity type labels, keyed by entity type name.
   */
  protected function getEntityTypeOptions(): array {
    $entity_types = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }

      // Remove bundleable entities that have no bundles declared.
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      if (empty($bundle_info)) {
        continue;
      }

      $entity_types[$entity_type_id] = $entity_type->getLabel();
    }

    return $entity_types;
  }

  /**
   * Builds a list of entity type bundle options.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   An array of bundle labels, keyed by bundle name.
   */
  protected function getEntityBundleOptions(EntityTypeInterface $entity_type): array {
    $bundle_options = [];
    // If the entity has bundles, allow option to restrict to bundle(s).
    if ($entity_type->hasKey('bundle')) {
      foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type->id()) as $bundle_id => $bundle_info) {
        $bundle_options[$bundle_id] = $bundle_info['label'];
      }
      natsort($bundle_options);
    }
    return $bundle_options;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->addDependencies(parent::calculateDependencies());

    // Entity type module dependencies.
    $entity_type_id = $this->getConfigurationValue('entity_type');
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $this->addDependency('module', $entity_type->getProvider());

    // Bundle dependencies.
    foreach ($this->getConfigurationValue('bundles') as $bundle) {
      $bundle_dependency = $entity_type->getBundleConfigDependency($bundle);
      $this->addDependency($bundle_dependency['type'], $bundle_dependency['name']);
    }

    // Entity browser dependencies.
    $entity_browser = $this->getConfigurationValue('entity_browser');
    if ($entity_browser && $this->entityTypeManager->hasDefinition('entity_browser')) {
      $browser = $this->entityTypeManager->getStorage('entity_browser')->load($entity_browser);
      if ($browser) {
        $this->addDependency($browser->getConfigDependencyKey(), $browser->getConfigDependencyName());
      }
    }

    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultIconUrl() {
    return $this->fileUrlGenerator->generateAbsoluteString(
      $this->moduleExtensionList->getPath('oe_oembed') . '/js/plugins/oe_oembed_entities/embed.png'
    );
  }

}
