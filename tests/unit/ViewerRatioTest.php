<?php
/**
 * @package Bindr
 */

namespace Bindr\Tests\Unit;

use Bindr\Tests\TestCase;
use Bindr_Viewer;
use ReflectionMethod;

class ViewerRatioTest extends TestCase {

	private function resolve_ratio( $option, $page_size ) {
		$method = new ReflectionMethod( Bindr_Viewer::class, 'resolve_ratio' );
		return $method->invoke( new Bindr_Viewer(), $option, $page_size );
	}

	public function test_numeric_option_wins_over_page_size() {
		$this->assertSame( 1.5, $this->resolve_ratio( '1.5', '595x842' ) );
	}

	public function test_auto_derives_ratio_from_page_size() {
		$this->assertSame( round( 842 / 595, 4 ), $this->resolve_ratio( 'auto', '595x842' ) );
	}

	public function test_auto_without_page_size_falls_back_to_a4() {
		$this->assertSame( 1.414, $this->resolve_ratio( 'auto', '' ) );
	}

	public function test_malformed_page_size_falls_back_to_a4() {
		$this->assertSame( 1.414, $this->resolve_ratio( 'auto', 'not-a-size' ) );
	}

	public function test_zero_width_page_size_falls_back_to_a4() {
		$this->assertSame( 1.414, $this->resolve_ratio( 'auto', '0x842' ) );
	}
}
