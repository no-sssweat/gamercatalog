<?php

declare(strict_types=1);

namespace Drupal\Tests\seven\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the Layout Builder module.
 *
 * @group seven
 */
class SevenLayoutBuilderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'seven';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'layout_builder',
    'layout_builder_views_test',
    'layout_test',
    'field_ui',
    'block',
    'block_test',
    'node',
    'layout_builder_test',
  ];

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   *   An error occurred when setting up the test.
   */
  protected function setUp(): void {
    $block_settings = ['region' => 'header'];

    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
      'access contextual links',
    ]);

    $this->drupalPlaceBlock('local_tasks_block', $block_settings);
    $this->drupalPlaceBlock('page_title_block', $block_settings);
    $this->createContentType([
      'type' => 'bundle_with_section_field',
      'name' => 'Bundle with section field',
    ]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The second node title',
      'body' => [
        [
          'value' => 'The second node body',
        ],
      ],
    ]);
  }

  /**
   * Tests the layout builder has the expected contextual links.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   *   An assertion failed.
   */
  public function testContextualLinks() {
    $route_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet("$route_prefix/display/default");
    $this->assertSession()->linkNotExists('Manage layout');
    $this->assertSession()->fieldDisabled('layout[allow_custom]');
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');

    $this->assertSession()->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $this->assertSession()->linkExists('Add block');
    $this->clickLink('Add block');
    $this->assertSession()->linkExists('Powered by Drupal');
    $this->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'This is the label');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');
    $this->assertSession()->elementExists('css', 'div.block-system-powered-by-block.contextual-region div[data-contextual-id]');
    $this->assertSession()->elementExists('css', 'div.block-page-title-block');
    $this->assertSession()->elementNotExists('css', 'div.block-page-title-block.contextual-region div[data-contextual-id]');
  }

}
