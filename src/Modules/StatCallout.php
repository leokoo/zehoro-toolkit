<?php
namespace Zehoro\Modules;

use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Stat Callout module.
 *
 * Registers the lkst/stat-callout Gutenberg block (server-side rendered).
 * Large bold number + label, with optional description and source citation.
 * Inspired by B2B/SaaS content marketing patterns ("10,000+ users").
 *
 * @package Zehoro\Modules
 */
class StatCallout implements ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'stat_callout', self::class, [
            'title'   => 'Stat Callout',
            'desc'    => 'Large bold number + label for B2B/SaaS content marketing. Example: "10,000+ users — trusted by marketers worldwide."',
            'default' => true,
        ] );
    }

    public function init(): void {
        add_action( 'init', [ $this, 'register_block' ] );
    }

    public function register_block(): void {
        // block.json declares editorScript: "file:./index.js"; register_block_type
        // auto-enqueues build/stat-callout/index.js with deps from index.asset.php.
        register_block_type(
            ZEHORO_DIR . 'build/stat-callout',
            [ 'render_callback' => [ $this, 'render' ] ]
        );
    }

    // ── Frontend render ──────────────────────────────────────────────────────

    public function render( array $attributes ): string {
        $stat    = sanitize_text_field( $attributes['stat']    ?? '' );
        $label   = sanitize_text_field( $attributes['label']   ?? '' );
        $desc    = sanitize_text_field( $attributes['desc']    ?? '' );
        $source  = sanitize_text_field( $attributes['source']  ?? '' );
        $sourceUrl = esc_url_raw(       $attributes['sourceUrl'] ?? '' );
        $layout  = sanitize_text_field( $attributes['layout']  ?? 'centered' );

        if ( empty( $stat ) ) return '';

        $props = get_block_wrapper_attributes( [
            'class' => 'lkst-stat-callout lkst-stat-callout--' . esc_attr( $layout ),
        ] );

        ob_start();
        ?>
        <div <?php echo $props; ?>>
            <div class="lkst-stat-number"><?php echo esc_html( $stat ); ?></div>
            <?php if ( ! empty( $label ) ) : ?>
            <div class="lkst-stat-label"><?php echo esc_html( $label ); ?></div>
            <?php endif; ?>
            <?php if ( ! empty( $desc ) ) : ?>
            <p class="lkst-stat-desc"><?php echo esc_html( $desc ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $source ) ) : ?>
            <p class="lkst-stat-source">
                <?php if ( ! empty( $sourceUrl ) ) : ?>
                <a href="<?php echo esc_url( $sourceUrl ); ?>" rel="noopener nofollow" target="_blank"><?php echo esc_html( $source ); ?></a>
                <?php else : ?>
                <?php echo esc_html( $source ); ?>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
