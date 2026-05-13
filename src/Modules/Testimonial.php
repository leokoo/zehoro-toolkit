<?php
namespace LK\SiteToolkit\Modules;

use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Testimonial module.
 *
 * Registers the lkst/testimonial Gutenberg block (server-side rendered).
 * Static testimonial card: photo, name, role/company, quote.
 * For E-E-A-T and social proof on corporate / SaaS content.
 *
 * @package LK\SiteToolkit\Modules
 */
class Testimonial implements ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'testimonial', self::class, [
            'title'   => 'Testimonial',
            'desc'    => 'Static testimonial card: photo, name, role/company, and quote. E-E-A-T and social proof for corporate sites.',
            'default' => true,
        ] );
    }

    public function init(): void {
        add_action( 'init', [ $this, 'register_block' ] );
    }

    public function register_block(): void {
        // block.json declares editorScript: "file:./index.js"; register_block_type
        // auto-enqueues build/testimonial/index.js with deps from index.asset.php.
        register_block_type(
            LKST_DIR . 'build/testimonial',
            [ 'render_callback' => [ $this, 'render' ] ]
        );
    }

    // ── Frontend render ──────────────────────────────────────────────────────

    public function render( array $attributes ): string {
        $quote    = sanitize_textarea_field( $attributes['quote']    ?? '' );
        $name     = sanitize_text_field(     $attributes['name']     ?? '' );
        $role     = sanitize_text_field(     $attributes['role']     ?? '' );
        $company  = sanitize_text_field(     $attributes['company']  ?? '' );
        $imageUrl = esc_url_raw(             $attributes['imageUrl'] ?? '' );
        $layout   = sanitize_text_field(     $attributes['layout']   ?? 'card' );

        if ( empty( $quote ) ) return '';

        $byline = implode( ', ', array_filter( [ $name, $role, $company ] ) );

        $props = get_block_wrapper_attributes( [
            'class' => 'lkst-testimonial lkst-testimonial--' . esc_attr( $layout ),
        ] );

        ob_start();
        ?>
        <figure <?php echo $props; ?>>
            <?php if ( ! empty( $imageUrl ) ) : ?>
            <div class="lkst-testimonial-avatar">
                <img src="<?php echo esc_url( $imageUrl ); ?>"
                     alt="<?php echo esc_attr( $name ); ?>"
                     loading="lazy" width="64" height="64">
            </div>
            <?php endif; ?>
            <blockquote class="lkst-testimonial-quote">
                <p><?php echo esc_html( $quote ); ?></p>
            </blockquote>
            <?php if ( ! empty( $byline ) ) : ?>
            <figcaption class="lkst-testimonial-byline">
                <?php if ( ! empty( $name ) ) : ?>
                <strong class="lkst-testimonial-name"><?php echo esc_html( $name ); ?></strong>
                <?php endif; ?>
                <?php if ( ! empty( $role ) || ! empty( $company ) ) : ?>
                <span class="lkst-testimonial-role"><?php echo esc_html( implode( ', ', array_filter( [ $role, $company ] ) ) ); ?></span>
                <?php endif; ?>
            </figcaption>
            <?php endif; ?>
        </figure>
        <?php
        return ob_get_clean();
    }
}
