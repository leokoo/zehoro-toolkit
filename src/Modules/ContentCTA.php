<?php
namespace LK\SiteToolkit\Modules;

use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Content CTA Module
 *
 * Handles Power CTA (top + bottom), Middle CTA, and Sidebar CTA injection.
 * Admin UI (settings page, meta box) lives in CTAAdmin to keep this class
 * focused on a single responsibility.
 *
 * @package LK\SiteToolkit\Modules
 */
class ContentCTA implements ModuleInterface {
    public function init(): void {
        // Priority 9: runs BEFORE wp_review_inject_data (priority 10), which appends
        // the WP Review Pro review box to the_content. Without this ordering the bottom
        // power CTA word-count includes review box text and injects INSIDE the review box.
        // Admin hooks (settings registration, meta box) are handled by CTAAdmin.
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

        // Disable injection inside any builder preview
        if ( isset($_GET['bricks']) || isset($_GET['etchwp']) || isset($_GET['elementor-preview']) ) return $content;

        $settings = get_option( 'lkst_content_cta_settings', [] );
        if ( empty( $settings ) ) return $content;

        $post_type    = get_post_type();
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
                $target_words  = ( (int) $power['bottom_percent'] / 100 ) * $word_count;
                $current_words = 0;
                $bottom_pos    = -1;

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
        $max_depth   = -1;

        foreach ( $cats as $cat ) {
            if ( isset( $slot_data['cat_overrides'][ $cat->term_id ] ) ) {
                $ancestors = get_ancestors( $cat->term_id, $cat->taxonomy );
                $depth     = count( $ancestors );
                if ( $depth > $max_depth ) {
                    $max_depth   = $depth;
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
        $max_depth   = -1;

        foreach ( $cats as $cat ) {
            if ( isset( $config['cat_rules'][ $cat->term_id ] ) ) {
                $ancestors = get_ancestors( $cat->term_id, $cat->taxonomy );
                $depth     = count( $ancestors );
                if ( $depth > $max_depth ) {
                    $max_depth   = $depth;
                    $deepest_cat = $cat->term_id;
                }
            }
        }
        return ( $deepest_cat !== null ) ? $config['cat_rules'][ $deepest_cat ] : false;
    }

    private function get_hierarchical_terms( $post_id ) {
        $tax_objs   = get_object_taxonomies( get_post_type( $post_id ), 'objects' );
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
                $max_depth   = -1;
                foreach ( $cats as $cat ) {
                    $ancestors = get_ancestors( $cat->term_id, $cat->taxonomy );
                    $depth     = count( $ancestors );
                    if ( isset( $cta_data['cat_overrides'][ $cat->term_id ] ) ) {
                        if ( $depth > $max_depth ) {
                            $max_depth   = $depth;
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
}