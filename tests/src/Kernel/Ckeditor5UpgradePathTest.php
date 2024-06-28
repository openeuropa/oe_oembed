<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_oembed\Kernel;

use Drupal\editor\Entity\Editor;
use Drupal\embed\Entity\EmbedButton;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\ckeditor5\Kernel\SmartDefaultSettingsTest;

/**
 * Tests the upgrade to CKEditor 5.
 *
 * @see \Drupal\Tests\entity_embed\Kernel\UpgradePathTest
 */
class Ckeditor5UpgradePathTest extends SmartDefaultSettingsTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'embed',
    'node',
    'oe_oembed',
    'ckeditor',
    'codesnippet',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'embed',
      'oe_oembed',
    ]);

    EmbedButton::create([
      'id' => 'node',
      'label' => 'Node',
      'type_id' => 'oe_oembed_entities',
      'type_settings' => [
        'entity_type' => 'node',
      ],
    ])->save();

    $filter_config_bad_filter_html = [
      'filter_html' => [
        'status' => 1,
        'settings' => [
          'allowed_html' => '<p> <br> <strong>',
        ],
      ],
    ];
    $filter_config_oe_oembed_filter_off = [
      'oe_oembed_filter' => [
        'status' => 0,
      ],
    ];
    $filter_config_oe_oembed_filter_on = [
      'oe_oembed_filter' => [
        'status' => 1,
      ],
    ];
    FilterFormat::create([
      'format' => 'oe_oembed_filter_disabled',
      'name' => 'Oembed disabled',
      'filters' => $filter_config_bad_filter_html + $filter_config_oe_oembed_filter_off,
    ])->save();
    FilterFormat::create([
      'format' => 'oe_oembed_filter_enabled_misconfigured_format_filter_html',
      'name' => 'Oembed enabled on a misconfigured format (filter_html wrong)',
      'filters' => $filter_config_bad_filter_html + $filter_config_oe_oembed_filter_on,
    ])->save();
    FilterFormat::create([
      'format' => 'oe_oembed_filter_enabled_misconfigured_format_missing_oe_oembed_filter',
      'name' => 'Oembed enabled on a misconfigured format (oe_oembed_filter missing)',
      'filters' => $filter_config_bad_filter_html + $filter_config_oe_oembed_filter_off,
    ])->save();
    FilterFormat::create([
      'format' => 'oe_oembed_filter_enabled',
      'name' => 'Oembed enabled on a well-configured format',
      'filters' => [
        'filter_html' => [
          'status' => 1,
          'settings' => [
            'allowed_html' => '<p> <br> <strong> <p data-oembed data-display-as> <a data-oembed data-display-as href>',
          ],
        ],
      ] + $filter_config_oe_oembed_filter_on,
    ])->save();

    $generate_editor_settings = function (bool $node_embed_button_in_toolbar) {
      return [
        'toolbar' => [
          'rows' => [
            0 => [
              [
                'name' => 'Basic Formatting',
                'items' => [
                  'Bold',
                  'Format',
                ],
              ],
              [
                'name' => 'Embedding',
                'items' => $node_embed_button_in_toolbar
                  ? [
                    'node',
                  ]
                  : [],
              ],
            ],
          ],
        ],
        'plugins' => [],
      ];
    };

    Editor::create([
      'format' => 'oe_oembed_filter_disabled',
      'editor' => 'ckeditor',
      'settings' => $generate_editor_settings(FALSE),
    ])->save();
    Editor::create([
      'format' => 'oe_oembed_filter_enabled_misconfigured_format_filter_html',
      'editor' => 'ckeditor',
      'settings' => $generate_editor_settings(TRUE),
    ])->save();
    Editor::create([
      'format' => 'oe_oembed_filter_enabled_misconfigured_format_missing_oe_oembed_filter',
      'editor' => 'ckeditor',
      'settings' => $generate_editor_settings(TRUE),
    ])->save();
    Editor::create([
      'format' => 'oe_oembed_filter_enabled',
      'editor' => 'ckeditor',
      'settings' => $generate_editor_settings(TRUE),
    ])->save();
  }

  /**
   * Tests the CKEditor 5 default settings conversion for oe_oembed.
   *
   * @param string $format_id
   *   The existing text format/editor pair to switch to CKEditor 5.
   * @param array $filters_to_drop
   *   An array of filter IDs to drop as the keys and either TRUE (fundamental
   *   compatibility error from CKEditor 5 expected) or FALSE (if optional to
   *   drop).
   * @param array $expected_ckeditor5_settings
   *   The CKEditor 5 settings to test.
   * @param string $expected_superset
   *   The default settings conversion may generate a superset of the original
   *   HTML restrictions. This lists the additional elements and attributes.
   * @param array $expected_fundamental_compatibility_violations
   *   All expected fundamental compatibility violations for the given text
   *   format.
   * @param string[] $expected_db_logs
   *   The expected database logs associated with the computed settings.
   * @param string[] $expected_messages
   *   The expected messages associated with the computed settings.
   * @param array|null $expected_post_filter_drop_fundamental_compatibility_violations
   *   All expected fundamental compatibility violations for the given text
   *   format, after dropping filters specified in $filters_to_drop.
   * @param array|null $expected_post_update_text_editor_violations
   *   All expected media and filter settings violations for the given text
   *   format.
   *
   * @dataProvider oembedProvider
   */
  public function testOembed(string $format_id, array $filters_to_drop, array $expected_ckeditor5_settings, string $expected_superset, array $expected_fundamental_compatibility_violations, array $expected_db_logs, array $expected_messages, ?array $expected_post_filter_drop_fundamental_compatibility_violations = NULL, ?array $expected_post_update_text_editor_violations = NULL): void {
    parent::test($format_id, $filters_to_drop, $expected_ckeditor5_settings, $expected_superset, $expected_fundamental_compatibility_violations, $expected_db_logs, $expected_messages, $expected_post_filter_drop_fundamental_compatibility_violations, $expected_post_update_text_editor_violations);
  }

  /**
   * The oe_oembed data provider.
   *
   * @return \Generator
   *   Test scenarios.
   */
  public static function oembedProvider() {
    $expected_ckeditor5_toolbar = [
      'items' => [
        'bold',
        '|',
        'node',
      ],
    ];

    yield "oe_oembed_filter disabled" => [
      'format_id' => 'oe_oembed_filter_disabled',
      'filters_to_drop' => [],
      'expected_ckeditor5_settings' => [
        'toolbar' => [
          'items' => [
            'bold',
          ],
        ],
        'plugins' => [],
      ],
      'expected_superset' => '',
      'expected_fundamental_compatibility_violations' => [],
      'expected_db_logs' => [],
      'expected_messages' => [],
    ];

    yield "oe_oembed_filter enabled on a misconfigured text format: filter_html wrong" => [
      'format_id' => 'oe_oembed_filter_enabled_misconfigured_format_filter_html',
      'filters_to_drop' => [],
      'expected_ckeditor5_settings' => [
        'toolbar' => $expected_ckeditor5_toolbar,
        'plugins' => [],
      ],
      'expected_superset' => '<p data-oembed data-display-as> <a data-oembed data-display-as href>',
      'expected_fundamental_compatibility_violations' => [],
      'expected_db_logs' => [],
      'expected_messages' => [
        'warning' => [
          'Updating to CKEditor 5 added support for some previously unsupported tags/attributes. A plugin introduced support for the following:  The tag <em class="placeholder">&lt;a&gt;</em>; These attributes: <em class="placeholder"> data-oembed (for &lt;p&gt;, &lt;a&gt;), data-display-as (for &lt;p&gt;, &lt;a&gt;), href (for &lt;a&gt;)</em>; Additional details are available in your logs.',
        ],
      ],
    ];

    yield "oe_oembed_filter enabled on a misconfigured text format: oe_oembed_filter off" => [
      'format_id' => 'oe_oembed_filter_enabled_misconfigured_format_missing_oe_oembed_filter',
      'filters_to_drop' => [],
      'expected_ckeditor5_settings' => [
        'toolbar' => $expected_ckeditor5_toolbar,
        'plugins' => [],
      ],
      'expected_superset' => '',
      'expected_fundamental_compatibility_violations' => [],
      'expected_db_logs' => [],
      'expected_messages' => [],
      'expected_post_filter_drop_fundamental_compatibility_violations' => NULL,
      'expected_post_update_text_editor_violations' => [
        'settings.toolbar.items.2' => 'The <em class="placeholder">Node</em> toolbar item requires the <em class="placeholder">Embeds entities using the oEmbed format</em> filter to be enabled.',
      ],
    ];

    yield "oe_oembed_filter enabled on a well-configured text format" => [
      'format_id' => 'oe_oembed_filter_enabled',
      'filters_to_drop' => [],
      'expected_ckeditor5_settings' => [
        'toolbar' => $expected_ckeditor5_toolbar,
        'plugins' => [],
      ],
      'expected_superset' => '',
      'expected_fundamental_compatibility_violations' => [],
      'expected_db_logs' => [],
      'expected_messages' => [],
    ];
  }

}
