<?php
namespace LK\SiteToolkit\Modules;
use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class LastUpdated implements ModuleInterface {
    public static function register(): void {
        Plugin::register_module( 'last_updated', self::class, [
            'title' => 'Last Updated Badge',
            'desc'  => 'Outputs a freshness signal. Use [lkst_last_updated] or enable auto-inject via settings.',
            'default' => true,
            'settings_page' => 'lkst-last-updated'
        ] );
    }
    public function init(): void {
        add_shortcode( 'lkst_last_updated', [ $this, 'render_shortcode' ] );
        add_action( 'wp_head', [ $this, 'output_schema' ] );
        add_filter( 'the_content', [ $this, 'auto_inject' ] );

        if ( is_admin() ) {
            $this->register_admin_hooks();
        }
    }
    private function register_admin_hooks(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
    }

    public function register_settings(): void {
        register_setting( 'lkst_last_updated_group', 'lkst_lu_auto_inject', [ 'default' => '0', 'sanitize_callback' => 'absint' ] );
        register_setting( 'lkst_last_updated_group', 'lkst_lu_threshold_days', [ 'default' => '30', 'sanitize_callback' => 'absint' ] );
        register_setting( 'lkst_last_updated_group', 'lkst_lu_schema', [ 'default' => '1', 'sanitize_callback' => 'absint' ] );
    }
    public function register_settings_page(): void {
        add_submenu_page( null, 'Last Updated', 'Last Updated', 'manage_options', 'lkst-last-updated', [ $this, 'render_page' ] );
    }
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Last Updated Badge', 'leokoo-site-toolkit' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_last_updated_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="lkst_lu_auto_inject"><?php esc_html_e( 'Auto Inject (Top of content)', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="checkbox" id="lkst_lu_auto_inject" name="lkst_lu_auto_inject" value="1" <?php checked(get_option('lkst_lu_auto_inject', '0')); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_lu_threshold_days"><?php esc_html_e( 'Threshold (Days)', 'leokoo-site-toolkit' ); ?></label></th>
                        <td>
                            <input type="number" id="lkst_lu_threshold_days" name="lkst_lu_threshold_days" value="<?php echo esc_attr(get_option('lkst_lu_threshold_days', '30')); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Only show the badge if the post was updated more than X days after it was published.', 'leokoo-site-toolkit'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lkst_lu_schema"><?php esc_html_e( 'Output dateModified Schema', 'leokoo-site-toolkit' ); ?></label></th>
                        <td>
                            <input type="checkbox" id="lkst_lu_schema" name="lkst_lu_schema" value="1" <?php checked(get_option('lkst_lu_schema', '1')); ?>>
                            <p class="description"><?php esc_html_e('Injects structured data into the <head> to alert Google to the fresh content.', 'leokoo-site-toolkit'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    public function render_shortcode(): string {
        $post_id = get_the_ID();
        if ( ! $post_id ) return '';
        $pub_time = get_post_time( 'U', true, $post_id );
        $mod_time = get_post_modified_time( 'U', true, $post_id );
        $threshold = (int) get_option( 'lkst_lu_threshold_days', '30' ) * DAY_IN_SECONDS;
        
        if ( ( $mod_time - $pub_time ) < $threshold ) return '';

        return sprintf(
            '<div class="lkst-last-updated lkst-editorial-block" style="display:inline-block; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; padding:4px 10px; background:var(--lkst-bg-light, #F5F0E8); color:var(--lkst-primary-contrast, #0F1A2E); border-radius:3px; margin-bottom:1em;">Updated: %s</div>',
            esc_html( get_the_modified_date( '', $post_id ) )
        );
    }
    public function auto_inject( $content ) {
        if ( is_single() && in_the_loop() && is_main_query() && get_option( 'lkst_lu_auto_inject', '0' ) ) {
            $badge = $this->render_shortcode();
            if ( $badge ) {
                return $badge . $content;
            }
        }
        return $content;
    }
    public function output_schema(): void {
        if ( ! is_single() || ! get_option( 'lkst_lu_schema', '1' ) ) return;
        $post_id = get_the_ID();
        $pub_time = get_post_time( 'U', true, $post_id );
        $mod_time = get_post_modified_time( 'U', true, $post_id );
        $threshold = (int) get_option( 'lkst_lu_threshold_days', '30' ) * DAY_IN_SECONDS;
        
        if ( ( $mod_time - $pub_time ) < $threshold ) return;

        $schema = [
            '@context'     => 'https://schema.org',
            '@type'        => 'WebPage',
            'dateModified' => get_post_modified_time( 'c', true, $post_id )
        ];
        echo '<script type="application/ld+json" class="lkst-last-updated-schema">' . wp_json_encode( $schema ) . '</script>' . "\n";
    }
}