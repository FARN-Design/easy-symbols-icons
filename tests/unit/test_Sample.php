<?php
/**
 * Class SampleTest
 *
 * @package Easysearch
 */

namespace unit;

use Farn\EasySearch\util\TestUtil;
use WP_UnitTestCase;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

/**
 * Sample test case.
 */
class test_Sample extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_sample() {
		// Replace this with some actual testing code.
		$this->assertTrue( true );
	}

	public function test_sample2() {
		assertFalse( false);
	}
}
