<?php
/**
 * ContentBox — the CTA / email-capture box (also the ContentStream cta + email slots).
 *
 * Regression cover for a rename-drift bug: the email-capture box is rendered in
 * one place and wired up by inline JS + an AJAX handler elsewhere. A half-finished
 * lkst_* → zehoro_* rename left the form's class, its hidden field names, and the
 * JS/handler that consume them disagreeing — so the form silently did a full-page
 * reload and the download link + per-shortcode webhook were dropped. These pin the
 * render-side contract so any future drift fails loudly.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\ContentBox;

class ContentBoxTest extends WP_UnitTestCase {

	private function email_box(): string {
		return ( new ContentBox() )->render_shortcode( [
			'type'    => 'email',
			'heading' => 'Get the PDF',
			'webhook' => 'https://hook.example/x',
		] );
	}

	public function test_email_form_class_matches_its_js_submit_hook() {
		$html = $this->email_box();
		preg_match( '/<form class="([^"]*)"/', $html, $form );
		preg_match( "/classList\\.contains\\(\\'([^']+)\\'\\)/", $html, $js );

		$this->assertNotEmpty( $form[1] ?? '', 'the email form should have a class' );
		$this->assertNotEmpty( $js[1] ?? '', 'the inline JS should gate on a class' );
		$this->assertStringContainsString(
			$js[1],
			$form[1],
			'the email form must carry the class its inline JS listens for — otherwise the AJAX submit never fires and the form does a broken page reload'
		);
	}

	public function test_email_form_fields_match_what_the_handler_reads() {
		$html = $this->email_box();
		// handle_submission() reads zehoro_box_email / _name / _file_url / _webhook / _hp.
		foreach ( [ 'zehoro_box_email', 'zehoro_box_name', 'zehoro_box_file_url', 'zehoro_box_webhook', 'zehoro_box_hp' ] as $field ) {
			$this->assertStringContainsString( 'name="' . $field . '"', $html, "the form must send {$field} (the handler reads it)" );
		}
	}

	public function test_email_form_carries_the_nonce_and_action_the_handler_checks() {
		$html = $this->email_box();
		$this->assertStringContainsString( 'name="lkst_box_security"', $html, 'nonce field the handler verifies' );
		$this->assertStringContainsString( 'value="lkst_box_submit"', $html, 'AJAX action the handler is hooked to' );
	}

	public function test_handler_forwards_file_url_and_per_shortcode_webhook_from_the_form() {
		// The form posts these names; the handler used to read pre-rename names,
		// silently dropping the download link + the per-shortcode webhook override.
		$_POST['lkst_box_security'] = $_REQUEST['lkst_box_security'] = wp_create_nonce( 'lkst_box_nonce' );
		$_POST['zehoro_box_email']    = 'reader@example.com';
		$_POST['zehoro_box_file_url'] = 'https://files.example/guide.pdf';
		$_POST['zehoro_box_webhook']  = 'https://hook.example/per-shortcode';

		$captured = [];
		add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$captured ) {
			$captured = [ 'url' => $url, 'body' => $args['body'] ?? [] ];
			return [ 'response' => [ 'code' => 200 ], 'body' => 'ok' ];
		}, 10, 3 );

		// wp_send_json_*() calls a plain die() unless we're "doing ajax", in which
		// case it routes through the filterable wp_die — make that throw instead of
		// exiting the test process. (Filters are restored by tear_down.)
		add_filter( 'wp_doing_ajax', '__return_true' );
		$throw = static function () { throw new \WPDieException( 'json' ); };
		add_filter( 'wp_die_ajax_handler', static fn() => $throw );

		ob_start();
		try {
			( new ContentBox() )->handle_submission();
		} catch ( \WPDieException $e ) {
			// expected — wp_send_json_success() "dies".
		}
		ob_end_clean();

		$this->assertSame( 'https://hook.example/per-shortcode', $captured['url'] ?? '', 'per-shortcode webhook override reaches wp_remote_post' );
		$this->assertSame( 'https://files.example/guide.pdf', $captured['body']['file_url'] ?? '', 'the download file_url from the form reaches the webhook' );

		unset( $_POST['lkst_box_security'], $_REQUEST['lkst_box_security'], $_POST['zehoro_box_email'], $_POST['zehoro_box_file_url'], $_POST['zehoro_box_webhook'] );
	}
}
