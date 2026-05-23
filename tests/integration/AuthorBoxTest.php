<?php
/**
 * AuthorBox module — v1.5.2 empty-default-URL regression tests.
 *
 * v1.5.2 changed CTA URL defaults from "/blog/" and "#newsletter" to empty
 * strings so unconfigured installs don't render broken links. Verify:
 *   - Empty URL hides the button entirely
 *   - Non-empty URL renders the button
 *   - The lkst/author_box/cta_primary filter wins over the option
 *
 * @package LK\SiteToolkit\Tests\Integration
 */

class AuthorBoxTest extends WP_UnitTestCase {

    private int $author_id;

    public function set_up(): void {
        parent::set_up();
        $this->author_id = $this->factory->user->create( [
            'role'         => 'author',
            'display_name' => 'Test Author',
            'description'  => 'Test bio.',
        ] );
        // Ensure we start each test from clean option state
        delete_option( 'lkst_cta_primary_url' );
        delete_option( 'lkst_cta_primary_label' );
        delete_option( 'lkst_cta_secondary_url' );
        delete_option( 'lkst_cta_secondary_label' );
    }

    public function test_empty_primary_url_hides_button() {
        // No option set + empty default → button hidden
        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringNotContainsString(
            'lkst-ab-btn-primary',
            $output,
            'With empty URL default, primary button must be hidden'
        );
        $this->assertStringContainsString(
            'Test Author',
            $output,
            'Author name should still render even when CTA is hidden'
        );
    }

    public function test_configured_primary_url_renders_button() {
        update_option( 'lkst_cta_primary_url',   '/news-and-articles/' );
        update_option( 'lkst_cta_primary_label', 'Read more articles' );

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringContainsString( 'lkst-ab-btn-primary', $output );
        $this->assertStringContainsString( '/news-and-articles/', $output );
        $this->assertStringContainsString( 'Read more articles', $output );
    }

    public function test_filter_overrides_option() {
        update_option( 'lkst_cta_primary_url',   '/wrong/' );
        update_option( 'lkst_cta_primary_label', 'Wrong label' );

        $filter = function () {
            return [ 'label' => 'From filter', 'url' => '/from-filter/' ];
        };
        add_filter( 'lkst/author_box/cta_primary', $filter );

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringContainsString( '/from-filter/', $output );
        $this->assertStringContainsString( 'From filter', $output );
        $this->assertStringNotContainsString( '/wrong/', $output );

        remove_filter( 'lkst/author_box/cta_primary', $filter );
    }
}
