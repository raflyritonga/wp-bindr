<?php
/**
 * @package Bindr
 */

namespace Bindr\Tests\Unit;

use Bindr\Tests\TestCase;
use Bindr_Block;
use Bindr_Viewer;
use Brain\Monkey\Functions;

class BlockRenderTest extends TestCase {

	private $viewer;
	private $block;

	protected function setUp(): void {
		parent::setUp();

		$this->viewer = new class() extends Bindr_Viewer {
			public $captured;

			public function render( $args ) {
				$this->captured = $args;
				return '<div class="bindr-viewer"></div>';
			}
		};
		$this->block  = new Bindr_Block( $this->viewer );

		Functions\when( 'shortcode_atts' )->alias(
			static function ( $defaults, $atts ) {
				$atts = (array) $atts;
				$out  = array();
				foreach ( $defaults as $key => $default ) {
					$out[ $key ] = array_key_exists( $key, $atts ) ? $atts[ $key ] : $default;
				}
				return $out;
			}
		);
	}

	public function test_shortcode_normalizes_attributes() {
		$this->block->render_shortcode(
			array(
				'id'          => '12',
				'height_mode' => 'fixed',
				'height'      => '700',
				'display'     => 'single',
			)
		);

		$this->assertSame(
			array(
				'id'          => 12,
				'height_mode' => 'fixed',
				'height'      => 700,
				'display'     => 'single',
			),
			$this->viewer->captured
		);
	}

	public function test_shortcode_rejects_invalid_values() {
		$this->block->render_shortcode(
			array(
				'id'          => 'abc',
				'height_mode' => 'weird',
				'display'     => 'triple',
			)
		);

		$this->assertSame( 0, $this->viewer->captured['id'] );
		$this->assertSame( 'ratio', $this->viewer->captured['height_mode'] );
		$this->assertSame( '', $this->viewer->captured['display'] );
	}

	public function test_shortcode_defaults_without_attributes() {
		$this->block->render_shortcode( '' );

		$this->assertSame(
			array(
				'id'          => 0,
				'height_mode' => 'ratio',
				'height'      => 600,
				'display'     => '',
			),
			$this->viewer->captured
		);
	}

	public function test_block_maps_attributes_to_render_args() {
		$this->block->render_block(
			array(
				'flipbookId' => 7,
				'heightMode' => 'fixed',
				'height'     => 500,
				'display'    => 'double',
			)
		);

		$this->assertSame(
			array(
				'id'          => 7,
				'height_mode' => 'fixed',
				'height'      => 500,
				'display'     => 'double',
			),
			$this->viewer->captured
		);
	}

	public function test_block_defaults_for_missing_attributes() {
		$this->block->render_block( array() );

		$this->assertSame(
			array(
				'id'          => 0,
				'height_mode' => 'ratio',
				'height'      => 600,
				'display'     => '',
			),
			$this->viewer->captured
		);
	}
}
