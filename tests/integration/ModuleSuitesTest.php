<?php
/**
 * Module suites — the Kadence-Blocks-style collapse of commodity module groups
 * (Blocks / Schema / Reading & Trust) into a single card with sub-toggles.
 * Pins Dashboard::collapse_suites (the pure partition the grid renders from).
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Admin\Dashboard;

class ModuleSuitesTest extends WP_UnitTestCase {

	/** @return array<string,array<string,mixed>> */
	private function mods(): array {
		return [
			'callout'        => [ 'title' => 'Callout',        'group' => 'editorial_blocks', 'is_active' => true,  'tier' => 'free' ],
			'faq'            => [ 'title' => 'FAQ',            'group' => 'editorial_blocks', 'is_active' => false, 'tier' => 'free' ],
			'review_box'     => [ 'title' => 'Review Box',     'group' => 'editorial_blocks', 'is_active' => true,  'tier' => 'pro'  ],
			'article_schema' => [ 'title' => 'Article Schema', 'group' => 'schema',           'is_active' => true,  'tier' => 'free' ],
			'ctr_rescue'     => [ 'title' => 'CTR Rescue',     'group' => 'seo',              'is_active' => true,  'tier' => 'pro'  ],
		];
	}

	public function test_commodity_groups_collapse_into_suites() {
		$p = Dashboard::collapse_suites( $this->mods(), Dashboard::suite_defs() );

		// An SEO module stays a regular card; a block is folded away.
		$this->assertArrayHasKey( 'ctr_rescue', $p['regular'] );
		$this->assertArrayNotHasKey( 'callout', $p['regular'] );

		// Editorial blocks → one "Blocks" suite, counts correct (callout + review_box on, faq off).
		$this->assertArrayHasKey( 'editorial_blocks', $p['suites'] );
		$blocks = $p['suites']['editorial_blocks'];
		$this->assertSame( 'Blocks', $blocks['title'] );
		$this->assertSame( 3, $blocks['total'] );
		$this->assertSame( 2, $blocks['active'] );
		$member_slugs = array_column( $blocks['members'], 'slug' );
		$this->assertContains( 'callout', $member_slugs );
		$this->assertContains( 'faq', $member_slugs );

		// Schema also collapses.
		$this->assertArrayHasKey( 'schema', $p['suites'] );
	}

	public function test_site_with_no_suite_modules_has_no_suites() {
		$p = Dashboard::collapse_suites(
			[ 'ctr_rescue' => [ 'title' => 'CTR Rescue', 'group' => 'seo', 'is_active' => true ] ],
			Dashboard::suite_defs()
		);
		$this->assertSame( [], $p['suites'] );
		$this->assertArrayHasKey( 'ctr_rescue', $p['regular'] );
	}

	public function test_members_are_sorted_by_title() {
		$mods = [
			'zebra'  => [ 'title' => 'Zebra Block', 'group' => 'editorial_blocks', 'is_active' => true ],
			'apple'  => [ 'title' => 'Apple Block', 'group' => 'editorial_blocks', 'is_active' => true ],
		];
		$p = Dashboard::collapse_suites( $mods, Dashboard::suite_defs() );
		$titles = array_column( $p['suites']['editorial_blocks']['members'], 'title' );
		$this->assertSame( [ 'Apple Block', 'Zebra Block' ], $titles );
	}
}
