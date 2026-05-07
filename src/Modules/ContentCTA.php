<?php
namespace LK\SiteToolkit\Modules;

if ( ! defined( 'ABSPATH' ) ) exit;

class ContentCTA {
    public function init() {
        if ( is_admin() ) {
            add_action( 'admin_init',    [ $this, 'register_settings' ] );
            add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
            add_action( 'save_post',     [ $this, 'save_meta_box' ] );
        }
        // Priority 9: runs BEFORE wp_review_inject_data (priority 10), which appends
        // the WP Review Pro review box to the_content. Without this ordering the bottom
        // power CTA word-count includes review box text and injects INSIDE the review box.
        add_filter( 'the_content', [ $this, 'inject_ctas' ], 9 );
        add_shortcode( 'lkst_sidebar_cta', [ $this, 'sidebar_cta_shortcode' ] );
    }

    public static function get_defaults() {
        return [
            'post_types'        => [ 'post' ],
            'inject_post_types' => [ 'post' ],
            'power'          => [
                'enabled'          => true,
                'paragraph'        => 4,
                'bottom_enabled'   => true,
                'bottom_min_words' => 1500,
                'bottom_percent'   => 82,
                'bottom_custom'    => false,
                'bottom_eyebrow'   => '',
                'bottom_heading'   => '',
                'bottom_desc'      => '',
                'bottom_form'      => '',
                'bottom_layout'    => 'text',
                'bottom_image_url' => '',
                'eyebrow'          => 'Weekly Brief',
                'heading'          => 'Enjoying this? Join the newsletter.',
                'desc'             => '',
                'form'             => '',
                'layout'           => 'text',
                'image_url'        => '',
                'cat_overrides'    => [],
            ],
            'middle'         => [
                'enabled'       => false,
                'paragraph'     => 10,
                'min_words'     => 1000,
                'eyebrow'       => '',
                'heading'       => '',
                'desc'          => '',
                'form'          => '',
                'layout'        => 'text',
                'image_url'     => '',
                'cat_overrides' => [],
            ],
            'content_inject' => [
                'cat_rules' => [],
            ],
            'sidebar'        => [
                'eyebrow'       => '',
                'heading'       => '',
                'desc'          => '',
                'form'          => '',
                'layout'        => 'text',
                'image_url'     => '',
                'cat_overrides' => [],
            ],
        ];
    }

    public function register_settings() {
        register_setting( 'lkst_content_cta_group', 'lkst_content_cta_settings', [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => static::get_defaults(),
        ] );

        $settings = get_option( 'lkst_content_cta_settings', [] );
        $post_types = ! empty( $settings['post_types'] ) ? $settings['post_types'] : [ 'post' ];
        foreach ( $post_types as $pt ) {
            register_post_meta( $pt, 'lkst_no_cta', [
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'boolean',
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
            ] );
        }
    }

    public function add_meta_box() {
        $settings   = get_option( 'lkst_content_cta_settings', [] );
        $post_types = ! empty( $settings['post_types'] ) ? $settings['post_types'] : [ 'post' ];
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'lkst_no_cta_meta',
                'Content CTAs',
                [ $this, 'render_meta_box' ],
                $pt,
                'side',
                'default'
            );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'lkst_no_cta_nonce', 'lkst_no_cta_nonce' );
        $checked = (bool) get_post_meta( $post->ID, 'lkst_no_cta', true );
        ?>
        <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
            <input type="checkbox" name="lkst_no_cta" value="1" style="margin-top:3px;" <?php checked( $checked ); ?>>
            <span>Suppress all CTAs on this post</span>
        </label>
        <p class="description" style="margin-top:6px;font-size:12px;">When checked, no CTAs (Power, Middle, Sidebar) will appear on this post.</p>
        <?php
    }

    public function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['lkst_no_cta_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lkst_no_cta_nonce'] ) ), 'lkst_no_cta_nonce' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( ! empty( $_POST['lkst_no_cta'] ) ) {
            update_post_meta( $post_id, 'lkst_no_cta', true );
        } else {
            delete_post_meta( $post_id, 'lkst_no_cta' );
        }
    }

    public function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) return static::get_defaults();
        $defaults = static::get_defaults();
        $out      = [];

        // Post types
        $valid_pts = array_keys( get_post_types( [ 'public' => true ] ) );
        $out['post_types'] = [];
        if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            foreach ( $input['post_types'] as $pt ) {
                $pt = sanitize_key( $pt );
                if ( in_array( $pt, $valid_pts, true ) && $pt !== 'page' ) {
                    $out['post_types'][] = $pt;
                }
            }
        }
        $out['inject_post_types'] = [];
        if ( ! empty( $input['inject_post_types'] ) && is_array( $input['inject_post_types'] ) ) {
            foreach ( $input['inject_post_types'] as $pt ) {
                $pt = sanitize_key( $pt );
                if ( in_array( $pt, $valid_pts, true ) && $pt !== 'page' ) {
                    $out['inject_post_types'][] = $pt;
                }
            }
        }

        // Power CTA
        $power_in     = is_array( $input['power'] ?? null ) ? $input['power'] : [];
        $out['power'] = array_merge(
            $this->sanitize_cta_fields( $power_in ),
            [
                'enabled'          => ! empty( $power_in['enabled'] ),
                'paragraph'        => max( 1, min( 60, (int) ( $power_in['paragraph'] ?? $defaults['power']['paragraph'] ) ) ),
                'bottom_enabled'   => ! empty( $power_in['bottom_enabled'] ),
                'bottom_min_words' => max( 0, (int) ( $power_in['bottom_min_words'] ?? $defaults['power']['bottom_min_words'] ) ),
                'bottom_percent'   => max( 1, min( 100, (int) ( $power_in['bottom_percent'] ?? $defaults['power']['bottom_percent'] ) ) ),
                'bottom_custom'    => ! empty( $power_in['bottom_custom'] ),
                'bottom_eyebrow'   => sanitize_text_field( $power_in['bottom_eyebrow'] ?? '' ),
                'bottom_heading'   => sanitize_text_field( $power_in['bottom_heading'] ?? '' ),
                'bottom_desc'      => wp_kses_post( $power_in['bottom_desc'] ?? '' ),
                'bottom_form'      => sanitize_text_field( $power_in['bottom_form'] ?? '' ),
                'bottom_layout'    => $this->sanitize_layout( $power_in['bottom_layout'] ?? 'text' ),
                'bottom_image_url' => esc_url_raw( $power_in['bottom_image_url'] ?? '' ),
                'cat_overrides'    => $this->sanitize_cat_overrides( $power_in['cat_overrides'] ?? [] ),
            ]
        );

        // Middle CTA
        $middle_in     = is_array( $input['middle'] ?? null ) ? $input['middle'] : [];
        $out['middle'] = array_merge(
            $this->sanitize_cta_fields( $middle_in ),
            [
                'enabled'       => ! empty( $middle_in['enabled'] ),
                'paragraph'     => max( 1, min( 60, (int) ( $middle_in['paragraph'] ?? $defaults['middle']['paragraph'] ) ) ),
                'min_words'     => max( 0, (int) ( $middle_in['min_words'] ?? $defaults['middle']['min_words'] ) ),
                'cat_overrides' => $this->sanitize_cat_overrides( $middle_in['cat_overrides'] ?? [] ),
            ]
        );

        $out['content_inject'] = [ 'cat_rules' => [] ];

        // Sidebar CTA
        $sidebar_in     = is_array( $input['sidebar'] ?? null ) ? $input['sidebar'] : [];
        $out['sidebar'] = array_merge(
            $this->sanitize_cta_fields( $sidebar_in ),
            [
                'cat_overrides' => $this->sanitize_cat_overrides( $sidebar_in['cat_overrides'] ?? [] ),
            ]
        );

        return $out;
    }

    private function sanitize_cta_fields( $input ) {
        return [
            'eyebrow'   => sanitize_text_field( $input['eyebrow'] ?? '' ),
            'heading'   => sanitize_text_field( $input['heading'] ?? '' ),
            'desc'      => wp_kses_post( $input['desc'] ?? '' ),
            'form'      => sanitize_text_field( $input['form'] ?? '' ),
            'layout'    => $this->sanitize_layout( $input['layout'] ?? 'text' ),
            'image_url' => esc_url_raw( $input['image_url'] ?? '' ),
        ];
    }

    private function sanitize_cat_overrides( $input ) {
        $out = [];
        if ( ! is_array( $input ) ) return $out;
        foreach ( $input as $tid => $override ) {
            $tid = (int) $tid;
            if ( $tid <= 0 || ! is_array( $override ) ) continue;
            $s = $this->sanitize_cta_fields( $override );
            if ( empty( $s['heading'] ) && empty( $s['form'] ) && empty( $s['desc'] ) ) continue;
            $out[ $tid ] = $s;
        }
        return $out;
    }

    private function sanitize_layout( $value ) {
        return in_array( $value, [ 'text', 'image-left', 'image-right', 'image-top' ], true ) ? $value : 'text';
    }

    // --- Injection Logic ---

    public function inject_ctas( $content ) {
        if ( ! is_singular() || is_page() ) return $content;
        if ( ! in_the_loop() || ! is_main_query() ) return $content;

        // Disable injection inside any builder generic check
        if ( isset($_GET['bricks']) || isset($_GET['etchwp']) || isset($_GET['elementor-preview']) ) return $content;
        
        $settings = get_option( 'lkst_content_cta_settings', [] );
        if ( empty( $settings ) ) return $content;

        $post_type = get_post_type();
        $inject_types = $settings['inject_post_types'] ?? $settings['post_types'] ?? [];
        if ( ! in_array( $post_type, $inject_types ) ) return $content;

        if ( get_post_meta( get_the_ID(), 'lkst_no_cta', true ) ) return $content;

        // At filter priority 9 (before wpautop/WP Review Pro), Gutenberg content still
        // contains block comments like <!-- wp:paragraph -->. Strip them before counting
        // words so the percentage-based bottom CTA position is accurate.
        $content_for_count = preg_replace( '/<!--.*?-->/s', '', $content );
        $word_count = str_word_count( strip_tags( $content_for_count ) );
        $paragraphs = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

        $injections = [];

        $power = $settings['power'] ?? [];
        if ( ! empty( $power['enabled'] ) ) {
            $power_data = $this->get_cta_data_with_overrides( $power );
            if ( ! empty( $power_data['form'] ) || ! empty( $power_data['heading'] ) ) {
                $pos = ( (int) $power['paragraph'] * 2 ) - 1;
                if ( isset( $paragraphs[ $pos ] ) ) {
                    $injections[ $pos ][] = $this->render_cta_html( $power_data );
                }
            }
        }

        $middle = $settings['middle'] ?? [];
        if ( ! empty( $middle['enabled'] ) && $word_count >= ( $middle['min_words'] ?? 0 ) ) {
            $middle_data = $this->get_cta_data_with_overrides( $middle );
            if ( ! empty( $middle_data['form'] ) || ! empty( $middle_data['heading'] ) ) {
                $pos = ( (int) $middle['paragraph'] * 2 ) - 1;
                $power_top_pos = ( (int) ( $power['paragraph'] ?? 0 ) * 2 ) - 1;
                if ( $pos > $power_top_pos && isset( $paragraphs[ $pos ] ) ) {
                    $injections[ $pos ][] = $this->render_cta_html( $middle_data );
                }
            }
        }

        if ( ! empty( $power['bottom_enabled'] ) && $word_count >= ( $power['bottom_min_words'] ?? 0 ) ) {
            $bottom_data = $power;
            if ( ! empty( $power['bottom_custom'] ) ) {
                $valid_keys = ['eyebrow', 'heading', 'desc', 'form', 'layout', 'image_url'];
                foreach ( $valid_keys as $key ) {
                    if ( isset( $power[ "bottom_{$key}" ] ) ) {
                        $bottom_data[ $key ] = $power[ "bottom_{$key}" ];
                    }
                }
            }
            $bottom_data = $this->get_cta_data_with_overrides( $bottom_data );
            
            if ( ! empty( $bottom_data['form'] ) || ! empty( $bottom_data['heading'] ) ) {
                $target_words = ( (int) $power['bottom_percent'] / 100 ) * $word_count;
                $current_words = 0;
                $bottom_pos = -1;

                for ( $i = 0; $i < count( $paragraphs ); $i += 2 ) {
                    // Strip block comments for accurate word count (same reason as above)
                    $current_words += str_word_count( strip_tags( preg_replace( '/<!--.*?-->/s', '', $paragraphs[ $i ] ) ) );
                    if ( $current_words >= $target_words ) {
                        $bottom_pos = $i + 1;
                        break;
                    }
                }

                // Only include middle paragraph in the guard if middle CTA is actually enabled.
                $middle_guard_pos = ( ! empty( $middle['enabled'] ) )
                    ? ( (int) ( $middle['paragraph'] ?? 0 ) * 2 ) - 1
                    : -1;
                $last_pos = max( $middle_guard_pos, ( (int) ( $power['paragraph'] ?? 0 ) * 2 ) - 1 );
                if ( $bottom_pos > $last_pos && isset( $paragraphs[ $bottom_pos ] ) ) {
                    $injections[ $bottom_pos ][] = $this->render_cta_html( $bottom_data );
                }
            }
        }

        krsort( $injections );
        foreach ( $injections as $pos => $html_array ) {
            $paragraphs[ $pos ] .= implode( '', $html_array );
        }

        $content = implode( '', $paragraphs );

        $inject_rule = $this->get_injection_rule( $settings['content_inject'] ?? [] );
        if ( $inject_rule ) {
            $inject_html = $this->render_cta_html( $inject_rule );
            if ( $inject_rule['position'] === 'before' ) {
                $content = $inject_html . $content;
            } else {
                $content = $content . $inject_html;
            }
        }

        return $content;
    }

    private function get_cta_data_with_overrides( $slot_data ) {
        if ( empty( $slot_data['cat_overrides'] ) ) return $slot_data;
        $cats = $this->get_hierarchical_terms( get_the_ID() );
        if ( empty( $cats ) ) return $slot_data;

        $deepest_cat = null;
        $max_depth = -1;

        foreach ( $cats as $cat ) {
            if ( isset( $slot_data['cat_overrides'][ $cat->term_id ] ) ) {
                $ancestors = get_ancestors( $cat->term_id, $cat->taxonomy );
                $depth = count( $ancestors );
                if ( $depth > $max_depth ) {
                    $max_depth = $depth;
                    $deepest_cat = $cat->term_id;
                }
            }
        }

        if ( $deepest_cat !== null ) {
            return array_merge( $slot_data, $slot_data['cat_overrides'][ $deepest_cat ] );
        }
        return $slot_data;
    }

    private function get_injection_rule( $config ) {
        if ( empty( $config['cat_rules'] ) ) return false;
        $cats = $this->get_hierarchical_terms( get_the_ID() );
        if ( empty( $cats ) ) return false;

        $deepest_cat = null;
        $max_depth = -1;

        foreach ( $cats as $cat ) {
            if ( isset( $config['cat_rules'][ $cat->term_id ] ) ) {
                $ancestors = get_ancestors( $cat->term_id, $cat->taxonomy );
                $depth = count( $ancestors );
                if ( $depth > $max_depth ) {
                    $max_depth = $depth;
                    $deepest_cat = $cat->term_id;
                }
            }
        }
        return ( $deepest_cat !== null ) ? $config['cat_rules'][ $deepest_cat ] : false;
    }

    private function get_hierarchical_terms( $post_id ) {
        $tax_objs = get_object_taxonomies( get_post_type($post_id), 'objects' );
        $taxonomies = [];
        foreach ( $tax_objs as $tax_name => $tax_obj ) {
            if ( $tax_obj->hierarchical ) {
                $taxonomies[] = $tax_name;
            }
        }
        $cats = [];
        foreach ( $taxonomies as $tax ) {
            $terms = get_the_terms( $post_id, $tax );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $cats = array_merge( $cats, $terms );
            }
        }
        return $cats;
    }

    public function render_cta_html( $data, $extra_class = '' ) {
        if ( empty( $data['form'] ) && empty( $data['heading'] ) ) return '';

        $layout    = $data['layout'] ?? 'text';
        $img_url   = $data['image_url'] ?? '';
        $eyebrow   = $data['eyebrow'] ?? '';
        $heading   = $data['heading'] ?? '';
        $desc      = $data['desc'] ?? '';
        $shortcode = $data['form'] ?? ( $data['shortcode'] ?? '' );

        if ( strpos( $layout, 'image-' ) !== false && empty( $img_url ) ) {
            $layout = 'text';
        }

        $classes = ['lkst-midpost-cta'];
        $classes[] = esc_attr( $layout );
        if ( $extra_class ) $classes[] = esc_attr( $extra_class );

        if ( strpos( $layout, 'image-' ) !== false ) {
            $classes[] = 'has-image';
            $classes[] = 'hide-image-mobile';
        }

        $html = '<div class="' . implode( ' ', $classes ) . '">';

        if ( strpos( $layout, 'image-' ) !== false && ! empty( $img_url ) ) {
            $html .= '<div class="lkst-cta-image-wrapper"><img src="' . esc_url( $img_url ) . '" class="lkst-cta-image" alt=""></div>';
        }

        $html .= '<div class="lkst-midpost-cta__text">';
        if ( ! empty( $eyebrow ) ) {
            $html .= '<small class="lkst-midpost-cta__eyebrow">' . esc_html( $eyebrow ) . '</small>';
        }
        if ( ! empty( $heading ) ) {
            $html .= '<strong class="lkst-midpost-cta__heading">' . esc_html( $heading ) . '</strong>';
        }
        if ( ! empty( $desc ) ) {
            $html .= '<span class="lkst-midpost-cta__desc">' . wp_kses_post( $desc ) . '</span>';
        }
        $html .= '</div>';

        if ( ! empty( $shortcode ) ) {
            $html .= '<div class="lkst-midpost-cta__form">' . do_shortcode( $shortcode ) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    public function sidebar_cta_shortcode() {
        $settings = get_option( 'lkst_content_cta_settings', [] );
        if ( empty( $settings['sidebar'] ) ) return '';

        if ( is_page() ) return '';
        if ( is_singular() ) {
            $active_types = $settings['post_types'] ?? [ 'post' ];
            if ( ! in_array( get_post_type(), $active_types, true ) ) return '';
        }

        $cta_data = $settings['sidebar'];
        
        if ( is_singular() && ! empty( $cta_data['cat_overrides'] ) ) {
            $cats = $this->get_hierarchical_terms( get_the_ID() );
            if ( ! empty( $cats ) ) {
                $deepest_cat = null;
                $max_depth = -1;
                foreach ( $cats as $cat ) {
                    $ancestors = get_ancestors( $cat->term_id, $cat->taxonomy );
                    $depth = count( $ancestors );
                    if ( isset( $cta_data['cat_overrides'][ $cat->term_id ] ) ) {
                        if ( $depth > $max_depth ) {
                            $max_depth = $depth;
                            $deepest_cat = $cat->term_id;
                        }
                    }
                }
                if ( $deepest_cat ) {
                    $cta_data = array_merge( $cta_data, $cta_data['cat_overrides'][ $deepest_cat ] );
                }
            }
        }

        return $this->render_cta_html( $cta_data, 'lkst-sidebar-cta' );
    }

    // --- Admin rendering (used by Dashboard via callback) ---
    
    public function render_page() {
        $s        = get_option( 'lkst_content_cta_settings', [] );
        $defaults = static::get_defaults();
        $s        = array_replace_recursive( $defaults, is_array( $s ) ? $s : [] );

        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $pt_exclude = [ 'attachment', 'page', 'bricks_template', 'etch_template', 'elementor_library', 'ifso_triggers' ];
        
        $active_pts = $s['post_types'] ?? ['post'];
        $taxonomies = [];
        foreach ( $active_pts as $pt ) {
            $pt_taxonomies = get_object_taxonomies( $pt, 'objects' );
            foreach ( $pt_taxonomies as $tax_name => $tax_obj ) {
                if ( $tax_obj->public && $tax_obj->hierarchical ) {
                    $taxonomies[] = $tax_name;
                }
            }
        }
        $taxonomies = array_unique( $taxonomies );
        if ( empty($taxonomies) ) $taxonomies = ['category'];

        $categories = get_terms( [ 
            'taxonomy' => $taxonomies,
            'hide_empty' => false, 
            'number' => 500, 
            'orderby' => 'name', 
            'order' => 'ASC' 
        ] );
        if ( is_wp_error( $categories ) ) $categories = [];
        ?>
        <div class="wrap lkst-settings">
            <h1>Content CTAs</h1>
            <p>Unified Power + Middle + Sidebar CTA management. Pages are always excluded from injection.</p>

            <style>
                .lkst-tab-content { display: none; padding: 20px 0; }
                .lkst-tab-content.lkst-active { display: block; }
                .lkst-section { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px 24px; margin-bottom: 20px; }
                .lkst-section h3 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
                .lkst-field-row { display: flex; flex-direction: column; margin-bottom: 14px; }
                .lkst-field-row label { font-weight: 600; margin-bottom: 4px; }
                .lkst-field-row input[type="text"],
                .lkst-field-row input[type="number"],
                .lkst-field-row input[type="url"],
                .lkst-field-row select { max-width: 420px; }
                .lkst-field-row textarea { width: 100%; max-width: 580px; }
                .lkst-hint { color: #646970; font-size: 12px; margin-top: 3px; }
                .lkst-toggle-row { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
                .lkst-image-fields { margin-top: 10px; padding: 12px 16px; background: #f6f7f7; border-radius: 4px; }
                .lkst-bottom-custom-wrap { margin-top: 16px; padding: 16px; background: #f0f6fc; border-radius: 4px; border-left: 3px solid #007cba; }
                .lkst-override-body td { padding: 12px 16px 16px !important; background: #f9f9f9; }
            </style>

            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_content_cta_group' ); ?>

                <h2 class="nav-tab-wrapper" id="lkst-tab-nav">
                    <a href="#" class="nav-tab nav-tab-active" data-lkst-tab="lkst-general">General</a>
                    <a href="#" class="nav-tab" data-lkst-tab="lkst-power">Power CTA</a>
                    <a href="#" class="nav-tab" data-lkst-tab="lkst-middle">Middle CTA</a>
                    <a href="#" class="nav-tab" data-lkst-tab="lkst-inject">Content Injection</a>
                    <a href="#" class="nav-tab" data-lkst-tab="lkst-sidebar">Sidebar CTA</a>
                </h2>

                <div id="lkst-general" class="lkst-tab-content lkst-active">
                    <div class="lkst-section">
                        <h3>Active Post Types</h3>
                        <p class="description" style="margin-top:0;">Controls which post types show the sidebar CTA and have the Content CTAs admin meta box.</p>
                        <?php foreach ( $post_types as $slug => $pt ) :
                            if ( in_array( $slug, $pt_exclude, true ) ) continue; ?>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox"
                                       name="lkst_content_cta_settings[post_types][]"
                                       value="<?php echo esc_attr( $slug ); ?>"
                                       <?php checked( in_array( $slug, $s['post_types'], true ) ); ?>>
                                <?php echo esc_html( $pt->label ); ?>
                                <code style="margin-left:4px;"><?php echo esc_html( $slug ); ?></code>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="lkst-section">
                        <h3>Inline CTA Injection Post Types</h3>
                        <p class="description" style="margin-top:0;">Controls which post types get Power &amp; Middle CTAs injected into their content. Typically <code>post</code> only — review-style post types usually rely on the sidebar CTA instead.</p>
                        <?php foreach ( $post_types as $slug => $pt ) :
                            if ( in_array( $slug, $pt_exclude, true ) ) continue; ?>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox"
                                       name="lkst_content_cta_settings[inject_post_types][]"
                                       value="<?php echo esc_attr( $slug ); ?>"
                                       <?php checked( in_array( $slug, $s['inject_post_types'] ?? ['post'], true ) ); ?>>
                                <?php echo esc_html( $pt->label ); ?>
                                <code style="margin-left:4px;"><?php echo esc_html( $slug ); ?></code>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="lkst-power" class="lkst-tab-content">
                    <div class="lkst-section">
                        <h3>Power CTA &mdash; Top</h3>
                        <div class="lkst-toggle-row">
                            <input type="hidden" name="lkst_content_cta_settings[power][enabled]" value="0">
                            <input type="checkbox" id="power_enabled"
                                   name="lkst_content_cta_settings[power][enabled]" value="1"
                                   <?php checked( $s['power']['enabled'] ); ?>>
                            <label for="power_enabled"><strong>Enable Power CTA</strong></label>
                        </div>
                        <div class="lkst-field-row">
                            <label for="power_paragraph">Insert after paragraph #</label>
                            <input type="number" id="power_paragraph" style="width:80px"
                                   name="lkst_content_cta_settings[power][paragraph]"
                                   value="<?php echo (int) $s['power']['paragraph']; ?>" min="1" max="60">
                        </div>
                        <?php $this->render_cta_fields( 'lkst_content_cta_settings[power]', $s['power'] ); ?>
                    </div>

                    <div class="lkst-section">
                        <h3>Power CTA &mdash; Bottom</h3>
                        <div class="lkst-toggle-row">
                            <input type="hidden" name="lkst_content_cta_settings[power][bottom_enabled]" value="0">
                            <input type="checkbox" id="power_bottom_enabled"
                                   name="lkst_content_cta_settings[power][bottom_enabled]" value="1"
                                   <?php checked( $s['power']['bottom_enabled'] ); ?>>
                            <label for="power_bottom_enabled"><strong>Enable Bottom Power CTA</strong></label>
                        </div>
                        <div class="lkst-field-row">
                            <label for="power_bottom_min_words">Minimum word count to trigger</label>
                            <input type="number" id="power_bottom_min_words" style="width:100px"
                                   name="lkst_content_cta_settings[power][bottom_min_words]"
                                   value="<?php echo (int) $s['power']['bottom_min_words']; ?>" min="0">
                        </div>
                        <div class="lkst-field-row">
                            <label for="power_bottom_percent">Position &mdash; % of content reached</label>
                            <input type="number" id="power_bottom_percent" style="width:80px"
                                   name="lkst_content_cta_settings[power][bottom_percent]"
                                   value="<?php echo (int) $s['power']['bottom_percent']; ?>" min="10" max="95">
                        </div>
                        <div class="lkst-toggle-row" style="margin-top:16px;">
                            <input type="hidden" name="lkst_content_cta_settings[power][bottom_custom]" value="0">
                            <input type="checkbox" id="power_bottom_custom"
                                   name="lkst_content_cta_settings[power][bottom_custom]" value="1"
                                   <?php checked( $s['power']['bottom_custom'] ); ?>>
                            <label for="power_bottom_custom">Use different content for the bottom CTA</label>
                        </div>
                        <div class="lkst-bottom-custom-wrap" id="lkst-bottom-custom-fields"
                             style="<?php echo $s['power']['bottom_custom'] ? '' : 'display:none'; ?>">
                            <p style="margin-top:0;"><strong>Custom bottom CTA content</strong></p>
                            <?php $this->render_cta_fields( 'lkst_content_cta_settings[power]', $s['power'], 'bottom_' ); ?>
                        </div>
                    </div>

                    <div class="lkst-section">
                        <h3>Category Overrides</h3>
                        <?php $this->render_cat_overrides( 'power', $s['power']['cat_overrides'] ?? [], $categories ); ?>
                    </div>
                </div>

                <div id="lkst-middle" class="lkst-tab-content">
                    <div class="lkst-section">
                        <h3>Middle CTA</h3>
                        <div class="lkst-toggle-row">
                            <input type="hidden" name="lkst_content_cta_settings[middle][enabled]" value="0">
                            <input type="checkbox" id="middle_enabled"
                                   name="lkst_content_cta_settings[middle][enabled]" value="1"
                                   <?php checked( $s['middle']['enabled'] ); ?>>
                            <label for="middle_enabled"><strong>Enable Middle CTA</strong></label>
                        </div>
                        <div class="lkst-field-row">
                            <label for="middle_min_words">Minimum word count</label>
                            <input type="number" id="middle_min_words" style="width:100px"
                                   name="lkst_content_cta_settings[middle][min_words]"
                                   value="<?php echo (int) $s['middle']['min_words']; ?>" min="0">
                        </div>
                        <div class="lkst-field-row">
                            <label for="middle_paragraph">Insert after paragraph #</label>
                            <input type="number" id="middle_paragraph" style="width:80px"
                                   name="lkst_content_cta_settings[middle][paragraph]"
                                   value="<?php echo (int) $s['middle']['paragraph']; ?>" min="1" max="60">
                        </div>
                        <?php $this->render_cta_fields( 'lkst_content_cta_settings[middle]', $s['middle'] ); ?>
                    </div>
                    <div class="lkst-section">
                        <h3>Category Overrides</h3>
                        <?php $this->render_cat_overrides( 'middle', $s['middle']['cat_overrides'] ?? [], $categories ); ?>
                    </div>
                </div>

                <div id="lkst-inject" class="lkst-tab-content">
                    <div class="lkst-section">
                        <h3>Category-Based Content Injection</h3>
                        <p><em>🚧 Roadmap Item: Full per-category rule management coming in a future update.</em></p>
                    </div>
                </div>

                <div id="lkst-sidebar" class="lkst-tab-content">
                    <div class="lkst-section">
                        <h3>Sidebar CTA</h3>
                        <p>Output via <code>[lkst_sidebar_cta]</code>. Only renders on singular posts of the active post types.</p>
                        <?php $this->render_cta_fields( 'lkst_content_cta_settings[sidebar]', $s['sidebar'] ); ?>
                    </div>
                    <div class="lkst-section">
                        <h3>Category Overrides</h3>
                        <?php $this->render_cat_overrides( 'sidebar', $s['sidebar']['cat_overrides'] ?? [], $categories ); ?>
                    </div>
                </div>

                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function activateTab(id) {
                $('[data-lkst-tab]').removeClass('nav-tab-active');
                $('.lkst-tab-content').removeClass('lkst-active');
                $('[data-lkst-tab="' + id + '"]').addClass('nav-tab-active');
                $('#' + id).addClass('lkst-active');
                try { sessionStorage.setItem('lkst_cta_tab', id); } catch(e) {}
            }
            $('#lkst-tab-nav [data-lkst-tab]').on('click', function(e) {
                e.preventDefault();
                activateTab($(this).data('lkst-tab'));
            });
            try {
                var saved = sessionStorage.getItem('lkst_cta_tab');
                if (saved && $('#' + saved).length) activateTab(saved);
            } catch(e) {}

            $('#power_bottom_custom').on('change', function() {
                $('#lkst-bottom-custom-fields').toggle(this.checked);
            });

            $(document).on('change', '.lkst-layout-select', function() {
                $(this).closest('.lkst-cta-field-group').find('.lkst-image-fields').toggle($(this).val() !== 'text');
            });

            $(document).on('change', '.lkst-cat-override-toggle', function() {
                $('#' + $(this).data('target')).toggle(this.checked);
            });
        });
        </script>
        <?php
    }

    private function render_cta_fields( $base, $data, $prefix = '' ) {
        $fn = function( $field ) use ( $base, $prefix ) { return esc_attr( "{$base}[{$prefix}{$field}]" ); };
        $layout    = $data[ $prefix . 'layout' ]    ?? 'text';
        $image_url = $data[ $prefix . 'image_url' ] ?? '';
        $eyebrow   = $data[ $prefix . 'eyebrow' ]   ?? '';
        $heading   = $data[ $prefix . 'heading' ]   ?? '';
        $desc      = $data[ $prefix . 'desc' ]      ?? '';
        $form      = $data[ $prefix . 'form' ]      ?? '';
        $show_img  = ( $layout !== 'text' );
        ?>
        <div class="lkst-cta-field-group">
            <div class="lkst-field-row">
                <label>Layout</label>
                <select name="<?php echo $fn('layout'); ?>" class="lkst-layout-select">
                    <option value="text"        <?php selected( $layout, 'text' ); ?>>Text only</option>
                    <option value="image-left"  <?php selected( $layout, 'image-left' ); ?>>Image &mdash; Left</option>
                    <option value="image-right" <?php selected( $layout, 'image-right' ); ?>>Image &mdash; Right</option>
                    <option value="image-top"   <?php selected( $layout, 'image-top' ); ?>>Image &mdash; Top (full width)</option>
                </select>
            </div>
            <div class="lkst-image-fields" <?php echo $show_img ? '' : 'style="display:none"'; ?>>
                <div class="lkst-field-row" style="margin-bottom:0;">
                    <label>Image URL</label>
                    <input type="url" name="<?php echo $fn('image_url'); ?>"
                           value="<?php echo esc_attr( $image_url ); ?>"
                           placeholder="https://&hellip;" class="regular-text">
                </div>
            </div>
            <div class="lkst-field-row" style="margin-top:14px;">
                <label>Eyebrow</label>
                <input type="text" name="<?php echo $fn('eyebrow'); ?>" value="<?php echo esc_attr( $eyebrow ); ?>" class="regular-text">
            </div>
            <div class="lkst-field-row">
                <label>Heading</label>
                <input type="text" name="<?php echo $fn('heading'); ?>" value="<?php echo esc_attr( $heading ); ?>" class="regular-text">
            </div>
            <div class="lkst-field-row">
                <label>Description</label>
                <textarea name="<?php echo $fn('desc'); ?>" rows="3"><?php echo esc_textarea( $desc ); ?></textarea>
            </div>
            <div class="lkst-field-row">
                <label>Form shortcode</label>
                <input type="text" name="<?php echo $fn('form'); ?>" value="<?php echo esc_attr( $form ); ?>" class="regular-text">
            </div>
        </div>
        <?php
    }

    private function render_cat_overrides( $slot, $overrides, $cats ) {
        if ( empty( $cats ) ) {
            echo '<p><em>No categories found.</em></p>'; return;
        }
        ?>
        <table class="widefat striped" style="max-width:860px;">
            <thead><tr><th style="width:34px;">On</th><th>Category</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ( $cats as $cat ) :
                $cat_id   = (int) $cat->term_id;
                $override = $overrides[ $cat_id ] ?? null;
                $active   = ! empty( $override ) && ( ! empty( $override['heading'] ) || ! empty( $override['form'] ) || ! empty( $override['desc'] ) );
                $row_id   = esc_attr( "lkst-or-{$slot}-{$cat_id}" );
            ?>
                <tr>
                    <td><input type="checkbox" class="lkst-cat-override-toggle" data-target="<?php echo $row_id; ?>" <?php checked( $active ); ?>></td>
                    <td>
                        <strong><?php echo esc_html( $cat->name ); ?></strong>
                        <span style="color:#999;font-size:11px;"> (<?php echo esc_html( $cat->taxonomy ); ?>)</span>
                        <?php if ( $cat->parent ) echo '<span style="color:#999;font-size:11px;"> (sub)</span>'; ?>
                        <code style="font-size:11px;margin-left:6px;"><?php echo esc_html( $cat->slug ); ?></code>
                    </td>
                    <td><?php echo $active ? '<span style="color:#00a32a;font-weight:600;">&#10003; Active</span>' : '<span style="color:#c3c4c7;">&#8211;</span>'; ?></td>
                </tr>
                <tr id="<?php echo $row_id; ?>" <?php echo $active ? '' : 'style="display:none"'; ?>>
                    <td></td>
                    <td colspan="2">
                        <?php $this->render_cta_fields( "lkst_content_cta_settings[{$slot}][cat_overrides][{$cat_id}]", $override ?? [] ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}