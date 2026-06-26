<?php
/**
 * ArticleSchema — the JSON-LD @type comes from the filterable post-type → schema-type
 * map (`zehoro_article_schema_type_map`), not a hardcoded value. A stray reassignment
 * used to clobber every non-'post' type back to 'Article', making the whole shipped map
 * (page→WebPage, recipe→Recipe, service→Service, …) and the filter dead. Pinned here.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\ArticleSchema;

class ArticleSchemaTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'zehoro_article_schema_type_map' );
		parent::tear_down();
	}

	public function test_type_uses_the_shipped_map_not_a_hardcoded_article() {
		$post = self::factory()->post->create_and_get( [ 'post_type' => 'post' ] );
		$this->assertSame( 'BlogPosting', ArticleSchema::build_schema( $post )['@type'] );

		// page → WebPage in the shipped map; before the fix this emitted a hardcoded 'Article'.
		$page = self::factory()->post->create_and_get( [ 'post_type' => 'page' ] );
		$this->assertSame( 'WebPage', ArticleSchema::build_schema( $page )['@type'], 'page must map to WebPage, not a hardcoded Article' );
	}

	public function test_type_respects_the_filter() {
		add_filter( 'zehoro_article_schema_type_map', static fn( $map ) => array_merge( (array) $map, [ 'page' => 'AboutPage' ] ) );
		$page = self::factory()->post->create_and_get( [ 'post_type' => 'page' ] );
		$this->assertSame( 'AboutPage', ArticleSchema::build_schema( $page )['@type'] );
	}
}
