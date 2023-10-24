<?php

namespace Drupal\Tests\google_analytics_counter\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the google analytics counter settings form.
 *
 * @group google_analytics_counter
 */
class GoogleAnalyticsCounterSettingsTest extends BrowserTestBase {

  use StringTranslationTrait;

  const ADMIN_SETTINGS_PATH = 'admin/config/system/google-analytics-counter';

  /**
   * A user with permission to create and edit books and to administer blocks.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'node', 'path_alias'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
  }

  /**
   * Verifies that the google analytics counter settings page works.
   */
  public function testGoogleAnalyticsCounterSettingsForm() {
    $this->container->get('module_installer')->install(['google_analytics_counter']);
    $this->resetAll();

    $this->config('google_analytics_counter.settings')
      ->set('general_settings.gac_type_page', 1)
      ->save();

    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'administer google analytics counter',
    ]);

    $this->drupalLogin($this->adminUser);

    // Create item(s) in the queue.
    $queue_name = 'google_analytics_counter_worker';
    $queue = \Drupal::queue($queue_name);

    // Enqueue an item for processing.
    $queue->createItem([$this->randomMachineName() => $this->randomMachineName()]);

    $this->drupalGet(self::ADMIN_SETTINGS_PATH);
    $assert = $this->assertSession();

    // Assert the status code.
    $assert->statusCodeEquals(200);
    // Assert Fields.
    $assert->fieldExists('cron_interval');
    $assert->fieldExists('chunk_to_fetch');
    $assert->fieldExists('cache_length');
    $assert->fieldExists('queue_time');
    $assert->fieldExists('start_date');
    $assert->fieldExists('custom_start_date');
    $assert->fieldExists('custom_end_date');

    $edit = [
      'cron_interval' => 0,
      'chunk_to_fetch' => 5000,
      'cache_length' => 24,
    ];

    // Post form. Assert response.
    $this->submitForm($edit, $this->t('Save configuration'));
    $assert->pageTextContains($this->t('The configuration options have been saved.'));
  }

}
