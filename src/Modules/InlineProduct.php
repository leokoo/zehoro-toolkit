<?php
namespace LK\SiteToolkit\Modules;

use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Inline Product Mention module.
 *
 * Registers the lkst/inline-product Gutenberg block (server-side rendered).
 * Compact horizontal product card for mid-content references:
 * thumbnail, name, one-liner, small CTA button.
 * Inspired by Azonpress widget-small template.
 *
 * @package LK\SiteToolkit\Modules
 */
class InlineProduct implements ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'inline_product', self::class, [
            'title'   => 'Inline Product Mention',
            'desc'    => 'Compact horizontal product card for mid-content references: thumbnail, name, one-liner description, and a small CTA button.',
            'default' => true,
        ] );
    }

    public function init(): void {
        add_action( 'init', [ $this, 'register_block' ] );
    }

    public function register_block(): void {
        // block.json declares editorScript: "file:./index.js"; register_block_type
        // auto-enqueues build/inline-product/index.js with deps from index.asset.php.
        register_block_type(
            LKST_DIR . 'build/inline-product',
            [ 'render_callback' => [ $this, 'render' ] ]
        );
    }

    // ── Frontend render ──────────────────────────────────────────────────────

    public function render( array $attributes ): string {
        $name      = sanitize_text_field( $attributes['name']      ?? '' );
        $desc      = sanitize_text_field( $attributes['desc']      ?? '' );
        $imageUrl  = esc_url_raw(         $attributes['imageUrl']  ?? '' );
        $imageAlt  = sanitize_text_field( $attributes['imageAlt']  ?? $name );
        $btnText   = sanitize_text_field( $attributes['btnText']   ?? 'Check Price' );
        $btnUrl    = esc_url_raw(         $attributes['btnUrl']    ?? '' );
        $btnRel    = sanitize_text_field( $attributes['btnRel']    ?? 'nofollow sponsored' );

        if ( empty( $name ) && empty( $btnUrl ) ) return '';

        $props = get_block_wrapper_attributes( [ 'class' => 'lkst-inline-product' ] );

        ob_start();
        ?>
        <div <?php echo $props; ?>>
            <?php if ( ! empty( $imageUrl ) ) : ?>
            <div class="lkst-inline-product-image">
                <img src="<?php echo esc_url( $imageUrl ); ?>"
                     alt="<?php echo esc_attr( $imageAlt ); ?>"
                     loading="lazy" width="80" height="80">
            </div>
            <?php endif; ?>
            <div class="lkst-inline-product-body">
                <?php if ( ! empty( $name ) ) : ?>
                <div class="lkst-inline-product-name"><?php echo esc_html( $name ); ?></div>
                <?php endif; ?>
                <?php if ( ! empty( $desc ) ) : ?>
                <div class="lkst-inline-product-desc"><?php echo esc_html( $desc ); ?></div>
                <?php endif; ?>
            </div>
            <?php if ( ! empty( $btnUrl ) ) : ?>
            <div class="lkst-inline-product-cta">
                <a href="<?php echo esc_url( $btnUrl ); ?>"
                   class="lkst-inline-product-btn"
                   rel="<?php echo esc_attr( $btnRel ); ?>"
                   target="_blank">
                    <?php echo esc_html( $btnText ); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
