<?php
/**
 * AuthorBox module — coverage for both render_box and render_socials.
 *
 * Original 3 tests covered the v1.5.2 empty-default-URL regression. This file
 * was expanded to also cover render_socials (the [lkst_author_socials]
 * shortcode) plus the secondary CTA + bio + tagline + chips behavior paths
 * in render_box. Those were 100+ lines of untested rendering surface.
 *
 * v1.5.2 changed CTA URL defaults from "/blog/" and "#newsletter" to empty
 * strings so unconfigured installs don't render broken links.
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
        // Clean any social meta from prior tests on the same factory user.
        foreach ( [ 'facebook', 'linkedin', 'x', 'youtube' ] as $net ) {
            delete_user_meta( $this->author_id, 'lkst_social_' . $net );
        }
        delete_user_meta( $this->author_id, 'lkst_author_tagline' );
        delete_user_meta( $this->author_id, 'lkst_chip_1' );
        delete_user_meta( $this->author_id, 'lkst_chip_2' );
        delete_user_meta( $this->author_id, 'lkst_chip_3' );
    }

    // -------------------------------------------------------------------------
    // render_box — CTA primary (v1.5.2 regression)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // render_box — secondary CTA (same regression class as primary)
    // -------------------------------------------------------------------------

    public function test_empty_secondary_url_hides_secondary_button() {
        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringNotContainsString( 'lkst-ab-btn-secondary', $output );
    }

    public function test_configured_secondary_url_renders_secondary_button() {
        update_option( 'lkst_cta_secondary_url',   '/subscribe/' );
        update_option( 'lkst_cta_secondary_label', 'Get the newsletter' );

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringContainsString( 'lkst-ab-btn-secondary', $output );
        $this->assertStringContainsString( '/subscribe/', $output );
        $this->assertStringContainsString( 'Get the newsletter', $output );
    }

    public function test_secondary_filter_overrides_option() {
        update_option( 'lkst_cta_secondary_url',   '/from-option/' );
        update_option( 'lkst_cta_secondary_label', 'From option' );

        $filter = function () {
            return [ 'label' => 'From filter', 'url' => '/from-filter/' ];
        };
        add_filter( 'lkst/author_box/cta_secondary', $filter );

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringContainsString( '/from-filter/', $output );
        $this->assertStringNotContainsString( '/from-option/', $output );

        remove_filter( 'lkst/author_box/cta_secondary', $filter );
    }

    public function test_primary_and_secondary_render_independently() {
        // Only secondary configured — primary stays hidden, secondary appears.
        update_option( 'lkst_cta_secondary_url',   '/subscribe/' );
        update_option( 'lkst_cta_secondary_label', 'Subscribe' );

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringNotContainsString( 'lkst-ab-btn-primary', $output );
        $this->assertStringContainsString( 'lkst-ab-btn-secondary', $output );
    }

    // -------------------------------------------------------------------------
    // render_box — identity (name, tagline, bio, chips, avatar)
    // -------------------------------------------------------------------------

    public function test_tagline_rendered_when_configured() {
        update_user_meta( $this->author_id, 'lkst_author_tagline', 'WordPress builder, since 2018.' );

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringContainsString( 'class="lkst-ab-tagline"', $output );
        $this->assertStringContainsString( 'WordPress builder, since 2018.', $output );
    }

    public function test_no_tagline_omits_tagline_element() {
        // No meta set — tagline span should not appear.
        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringNotContainsString( 'class="lkst-ab-tagline"', $output );
    }

    public function test_bio_rendered_with_nl2br() {
        // Author was set up with 'Test bio.' — replace with a multi-line bio.
        wp_update_user( [ 'ID' => $this->author_id, 'description' => "Line one.\nLine two." ] );

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringContainsString( 'class="lkst-ab-bio"', $output );
        $this->assertStringContainsString( 'Line one.', $output );
        $this->assertStringContainsString( 'Line two.', $output );
        // nl2br should have inserted a <br> between the lines.
        $this->assertMatchesRegularExpression( '/Line one\.\s*<br\s*\/?>\s*Line two\./', $output );
    }

    public function test_chips_rendered_in_order() {
        update_user_meta( $this->author_id, 'lkst_chip_1', 'WordPress' );
        update_user_meta( $this->author_id, 'lkst_chip_2', 'SEO' );
        update_user_meta( $this->author_id, 'lkst_chip_3', 'Lifetime deals' );

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringContainsString( 'class="lkst-ab-chips"', $output );
        // All three chips present.
        $this->assertSame( 3, substr_count( $output, 'class="lkst-ab-chip"' ) );
        // In source order.
        $pos1 = strpos( $output, 'WordPress' );
        $pos2 = strpos( $output, 'SEO' );
        $pos3 = strpos( $output, 'Lifetime deals' );
        $this->assertNotFalse( $pos1 );
        $this->assertLessThan( $pos2, $pos1 );
        $this->assertLessThan( $pos3, $pos2 );
    }

    public function test_no_chips_omits_chips_wrapper() {
        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringNotContainsString( 'class="lkst-ab-chips"', $output );
    }

    public function test_partial_chips_render_only_configured_slots() {
        update_user_meta( $this->author_id, 'lkst_chip_1', 'WordPress' );
        // chip_2 and chip_3 empty.

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertSame( 1, substr_count( $output, 'class="lkst-ab-chip"' ) );
        $this->assertStringContainsString( 'WordPress', $output );
    }

    // -------------------------------------------------------------------------
    // render_box — socials section (in-box)
    // -------------------------------------------------------------------------

    public function test_no_socials_omits_socials_section_in_box() {
        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringNotContainsString( 'lkst-ab-socials', $output );
        $this->assertStringNotContainsString( 'lkst-ab-social', $output );
    }

    public function test_configured_social_renders_in_box_with_correct_attributes() {
        update_user_meta( $this->author_id, 'lkst_social_linkedin', 'https://linkedin.com/in/leokoo' );

        $output = do_shortcode( '[lkst_author_box author_id="' . $this->author_id . '"]' );

        $this->assertStringContainsString( 'class="lkst-ab-socials"', $output );
        // In-box socials use lkst-ab-social class (different from standalone shortcode).
        $this->assertStringContainsString( 'lkst-ab-social lkst-social-linkedin', $output );
        $this->assertStringContainsString( 'href="https://linkedin.com/in/leokoo"', $output );
        $this->assertStringContainsString( 'target="_blank"', $output );
        $this->assertStringContainsString( 'rel="noopener noreferrer"', $output );
        $this->assertStringContainsString( 'aria-label="LinkedIn"', $output );
    }

    // -------------------------------------------------------------------------
    // render_socials — standalone shortcode (was completely untested before)
    // -------------------------------------------------------------------------

    public function test_socials_shortcode_returns_empty_when_no_urls_configured() {
        // Singular context with author available, but no social meta.
        $output = do_shortcode( '[lkst_author_socials author_id="' . $this->author_id . '"]' );

        $this->assertSame(
            '',
            $output,
            'No URLs configured → no wrapper, no label. Empty string keeps templates clean.'
        );
    }

    public function test_socials_shortcode_returns_empty_when_author_cannot_be_resolved() {
        // Without author_id attr and outside a singular post context, resolve_author_id
        // returns 0 and render_socials must bail.
        $output = do_shortcode( '[lkst_author_socials]' );

        $this->assertSame( '', $output );
    }

    public function test_socials_shortcode_renders_single_configured_platform() {
        update_user_meta( $this->author_id, 'lkst_social_linkedin', 'https://linkedin.com/in/leokoo' );

        $output = do_shortcode( '[lkst_author_socials author_id="' . $this->author_id . '"]' );

        // Wrapper + label present.
        $this->assertStringContainsString( 'class="lkst-author-socials"', $output );
        $this->assertStringContainsString( 'class="lkst-author-socials__label"', $output );
        $this->assertStringContainsString( '>Connect<', $output );

        // Single link with correct attributes.
        $this->assertStringContainsString( 'class="lkst-author-social-link lkst-social-linkedin"', $output );
        $this->assertStringContainsString( 'href="https://linkedin.com/in/leokoo"', $output );
        $this->assertStringContainsString( 'target="_blank"', $output );
        $this->assertStringContainsString( 'rel="noopener noreferrer"', $output );
        $this->assertStringContainsString( 'aria-label="LinkedIn"', $output );
        $this->assertStringContainsString( 'class="dashicons dashicons-linkedin"', $output );

        // Only one social link rendered.
        $this->assertSame( 1, substr_count( $output, 'lkst-author-social-link' ) );
    }

    public function test_socials_shortcode_renders_all_four_platforms_when_configured() {
        update_user_meta( $this->author_id, 'lkst_social_facebook', 'https://facebook.com/leokoo' );
        update_user_meta( $this->author_id, 'lkst_social_linkedin', 'https://linkedin.com/in/leokoo' );
        update_user_meta( $this->author_id, 'lkst_social_x',        'https://x.com/leokoo' );
        update_user_meta( $this->author_id, 'lkst_social_youtube',  'https://youtube.com/@leokoo' );

        $output = do_shortcode( '[lkst_author_socials author_id="' . $this->author_id . '"]' );

        $this->assertSame( 4, substr_count( $output, 'lkst-author-social-link' ) );
        $this->assertStringContainsString( 'lkst-social-facebook', $output );
        $this->assertStringContainsString( 'lkst-social-linkedin', $output );
        $this->assertStringContainsString( 'lkst-social-x',        $output );
        $this->assertStringContainsString( 'lkst-social-youtube',  $output );

        // X uses dashicons-twitter (the legacy Twitter dashicon).
        $this->assertStringContainsString( 'dashicons-twitter', $output );
    }

    public function test_socials_shortcode_escapes_unsafe_urls() {
        // esc_url should drop the javascript: protocol entirely.
        update_user_meta( $this->author_id, 'lkst_social_linkedin', 'javascript:alert(1)' );

        $output = do_shortcode( '[lkst_author_socials author_id="' . $this->author_id . '"]' );

        $this->assertStringNotContainsString( 'javascript:', $output, 'esc_url must strip dangerous protocols' );
    }

    public function test_socials_shortcode_ignores_other_unconfigured_platforms() {
        // Only LinkedIn — the other 3 must not appear.
        update_user_meta( $this->author_id, 'lkst_social_linkedin', 'https://linkedin.com/in/leokoo' );

        $output = do_shortcode( '[lkst_author_socials author_id="' . $this->author_id . '"]' );

        $this->assertStringNotContainsString( 'lkst-social-facebook', $output );
        $this->assertStringNotContainsString( 'lkst-social-x',        $output );
        $this->assertStringNotContainsString( 'lkst-social-youtube',  $output );
    }
}
