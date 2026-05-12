<?php
namespace LK\SiteToolkit\Modules;

use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class ReadingProgress implements ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'reading_progress', self::class, [
            'title'   => 'Reading Progress Bar',
            'desc'    => 'Displays a 3px sticky progress bar at the top of the screen that fills as the user scrolls.',
            'default' => true
        ] );
    }

    public function init(): void {
        add_action( 'wp_footer', [ $this, 'inject_scripts' ], 100 );
    }

    public function inject_scripts(): void {
        if ( ! is_single() ) return;

        // Skip if in a builder preview
        if ( isset( $_GET['bricks'] ) || isset( $_GET['etchwp'] ) || isset( $_GET['elementor-preview'] ) ) return;

        ?>
        <!-- LKST Reading Progress -->
        <div id="lkst-reading-progress-container" style="position:fixed;top:0;left:0;width:100%;height:3px;background:transparent;z-index:99999;pointer-events:none;">
            <div id="lkst-reading-progress-bar" style="height:100%;width:0;background:var(--lkst-primary-color, #E8A020);transition:width 0.1s ease-out;"></div>
        </div>
        <script>
        (function() {
            var progressBar = document.getElementById('lkst-reading-progress-bar');
            if (!progressBar) return;

            var updateProgress = function() {
                var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                var scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                var progress = scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0;
                progressBar.style.width = progress + '%';
            };

            window.addEventListener('scroll', function() {
                requestAnimationFrame(updateProgress);
            });
            window.addEventListener('resize', function() {
                requestAnimationFrame(updateProgress);
            });
            updateProgress(); // initialize
        })();
        </script>
        <?php
    }
}
