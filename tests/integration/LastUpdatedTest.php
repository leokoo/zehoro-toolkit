<?php
/**
 * LastUpdated module — v1.5.1 refactor regression tests.
 *
 * Covers:
 *   - Threshold gating (no badge if mod-pub gap is under the threshold)
 *   - Default variant is unstyled (no .lkst-last-updated--pill class)
 *   - variant="pill" applies the modifier class
 *   - Semantic <time datetime="…"> wrapper present
 *   - The legacy .lkst-editorial-block class is NOT in the wrapper (removed in v1.5.1)
 *
 * @package LK\SiteToolkit\Tests\Integration
 */

class LastUpdatedTest extends WP_UnitTestCase {

    /**
     * Helper: create a post whose modified time is well past the configured threshold.
     *
     * WordPress's wp_insert_post() always overwrites post_modified to "now" on
     * insert, ignoring the value we pass in. So we create the post, then update
     * the row directly via $wpdb to back-date publish and forward-date modified.
     */
    private function create_post_with_old_mod_date(): int {
        global $wpdb;

        $post_id = $this->factory->post->create();

        $published = gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) );
        $modified  = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );

        $wpdb->update(
            $wpdb->posts,
            [
                'post_date'         => $published,
                'post_date_gmt'     => $published,
                'post_modified'     => $modified,
                'post_modified_gmt' => $modified,
            ],
            [ 'ID' => $post_id ]
        );
        clean_post_cache( $post_id );

        // Simulate a real singular-template render context so get_the_ID() works.
        $this->go_to( get_permalink( $post_id ) );

        return $post_id;
    }

    public function test_under_threshold_returns_empty() {
        // Post created and modified within the same day — way under 30-day threshold
        $post_id = $this->factory->post->create();
        $this->go_to( get_permalink( $post_id ) );

        $output = do_shortcode( '[lkst_last_updated]' );
        $this->assertSame(
            '',
            $output,
            'Posts modified within threshold should produce no output'
        );
    }

    public function test_default_variant_is_unstyled() {
        $this->create_post_with_old_mod_date();
        $output = do_shortcode( '[lkst_last_updated]' );

        $this->assertNotEmpty( $output );
        $this->assertStringContainsString( 'class="lkst-last-updated"', $output );
        $this->assertStringNotContainsString(
            'lkst-last-updated--pill',
            $output,
            'Default variant must NOT include the pill modifier class'
        );
        $this->assertStringNotContainsString(
            'lkst-editorial-block',
            $output,
            'The legacy lkst-editorial-block class was removed in v1.5.1 and must not appear'
        );
    }

    public function test_pill_variant_adds_modifier_class() {
        $this->create_post_with_old_mod_date();
        $output = do_shortcode( '[lkst_last_updated variant="pill"]' );

        $this->assertStringContainsString( 'lkst-last-updated--pill', $output );
    }

    public function test_semantic_time_element_present() {
        $this->create_post_with_old_mod_date();
        $output = do_shortcode( '[lkst_last_updated]' );

        $this->assertMatchesRegularExpression(
            '/<time datetime="\d{4}-\d{2}-\d{2}[^"]+">/',
            $output,
            'Output must wrap the date in <time datetime="ISO-8601">'
        );
    }

    public function test_custom_label_attribute() {
        $this->create_post_with_old_mod_date();
        $output = do_shortcode( '[lkst_last_updated label="Last edited:"]' );

        $this->assertStringContainsString( 'Last edited:', $output );
        $this->assertStringNotContainsString( 'Updated:', $output );
    }

    public function test_empty_label_omits_prefix() {
        $this->create_post_with_old_mod_date();
        $output = do_shortcode( '[lkst_last_updated label=""]' );

        $this->assertStringNotContainsString( 'Updated:', $output );
    }
}
