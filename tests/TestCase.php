<?php
/**
 * Base test case wiring Brain Monkey into the PHPUnit lifecycle.
 *
 * @package Bindr
 */

namespace Bindr\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Monkey\Functions\stubTranslationFunctions();
		Monkey\Functions\stubEscapeFunctions();
		unset( $GLOBALS['bindr_test_settings'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['bindr_test_settings'], $GLOBALS['wpdb'] );
		Monkey\tearDown();
		parent::tearDown();
	}
}
