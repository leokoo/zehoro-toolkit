<?php
/**
 * assets/style.css — the TOC must not fight the host theme's type scale.
 *
 * Up to 1.37.0 the TOC pinned 12px / 15px / 0.9em sizes and a
 * `font-size: 12px !important` on the BROWSE label, so every site on a
 * token-based scale (ACSS) had to override it, the label only with another
 * !important. Pins: every TOC font size goes through an overridable
 * --zehoro-toc-* custom property, and no TOC rule uses !important.
 *
 * @package Zehoro\Tests\Integration
 */

class TocStylesheetTest extends WP_UnitTestCase {

	/**
	 * Every `selector { body }` rule whose selector names a zehoro-toc class.
	 * Innermost blocks only, so rules inside @media are matched on their own.
	 *
	 * @return array<string,string> selector => declarations
	 */
	private function toc_rules(): array {
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/style.css' );
		$this->assertIsString( $css );
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );

		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER );
		$rules = [];
		foreach ( $m as $rule ) {
			$selector = trim( $rule[1] );
			if ( strpos( $selector, 'zehoro-toc' ) !== false ) {
				$rules[ $selector ] = $rule[2];
			}
		}
		return $rules;
	}

	public function test_finds_the_toc_rules() {
		// Guards against a vacuous pass if the stylesheet moves or is renamed.
		$this->assertArrayHasKey( '.zehoro-toc-title', $this->toc_rules() );
	}

	public function test_toc_font_sizes_go_through_custom_properties() {
		foreach ( $this->toc_rules() as $selector => $body ) {
			preg_match_all( '/font-size\s*:\s*([^;]+)/', $body, $sizes );
			foreach ( $sizes[1] as $value ) {
				$this->assertMatchesRegularExpression(
					'/^var\(\s*--zehoro-toc-[a-z-]+\s*,/',
					trim( $value ),
					"{$selector} sets font-size: {$value} — use an overridable --zehoro-toc-* property, never a fixed size."
				);
			}
		}
	}

	public function test_toc_rules_never_use_important() {
		foreach ( $this->toc_rules() as $selector => $body ) {
			$this->assertStringNotContainsString(
				'!important',
				$body,
				"{$selector} uses !important — a theme can then only beat it with another !important."
			);
		}
	}
}
