<?php

declare(strict_types=1);

namespace Drupal\Tests\seven\FunctionalJavaScript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\UserInterface;

/**
 * Tests Seven's vertical tabs rendering.
 *
 * @group seven
 */
class SevenVerticalTabsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'seven';

  /**
   * A user with administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   *   An error occurred while setting up the test.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(admin: TRUE);
  }

  /**
   * Tests that the vertical tabs are correctly rendered.
   */
  public function testVerticalTabs(): void {
    $expected_labels = [
      'Welcome (new user created by administrator)',
      'Welcome (awaiting approval)',
      'Password recovery',
    ];

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/people/accounts');
    $this->assertSession()->waitForElementVisible('css', '.vertical-tabs__menu');

    $labels = $this->xpath('//ul[contains(@class, "vertical-tabs__menu")]//strong[contains(@class, "vertical-tabs__menu-item-title")]');
    $text_labels = array_map(fn($el) => trim($el->getText()), $labels);

    foreach ($expected_labels as $label) {
      $this->assertContains($label, $text_labels);
    }
  }

}
