<?php
/**
 * This file is part of the MediaWiki extension CIForms.
 *
 * CIForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * CIForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CIForms.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <thomas.topway.it@mail.com>
 * @copyright Copyright © 2021-2022, https://wikisphere.org
 */

use Dompdf\Dompdf;
use PHPMailer\PHPMailer\PHPMailer;

if ( is_readable( __DIR__ . '/../../vendor/autoload.php' ) ) {
	include_once __DIR__ . '/../../vendor/autoload.php';
}

class CIFormsSubmit extends SpecialPage {
	public function __construct() {
		// not listed in the special pages index
		parent::__construct( 'CIFormsSubmit', '', false );
	}

	/**
	 * @param string|null $par
	 * @return void
	 */
	public function execute( $par ) {
		// $request = $this->getRequest();
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();
		// $out->addModuleStyles( 'ext.CIForms.validation' );

		// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		$post = $_POST;
		// $request->getArray( 'data' );

		global $wgCIFormsDisableEmailSending;
		global $wgEnableEmail;
		global $wgCIFormsSenderEmail;

		if ( CIForms::isCaptchaEnabled() ) {
			list( $result, $message, $captcha_message ) =
				$this->check_captcha( $post ) + [ null, null, null ];

			// @phan-suppress-next-line PhanSuspiciousValueComparison
			if ( $result === false ) {
				return $this->exit( $out,
					$this->msg( $message, $captcha_message, $wgCIFormsSenderEmail ) );
			}
		}

		$form_result = $this->parseForm( $post );

		if ( empty( $form_result['form_values'] ) ) {
			return $this->exit( $out, "no submission data" );
		}

		$row_inserted = $this->storeSubmission( $form_result );

		if ( !$wgEnableEmail || $wgCIFormsDisableEmailSending || !class_exists( 'PHPMailer\PHPMailer\PHPMailer' ) || !class_exists( 'Dompdf\Dompdf' ) ) {
			return $this->exit( $out, $this->exit_message( $form_result, $row_inserted, false ) );
		}

		global $wgCIFormsSubmitEmail;
		$submit = [];

		if ( !empty( $form_result['form_values']['submit'] ) ) {
			$submit = explode( ',', $form_result['form_values']['submit'] );
		} else {
			if ( !empty( $wgCIFormsSubmitEmail ) ) {
				$submit = explode( ',', $wgCIFormsSubmitEmail );
			}
		}

		if ( empty( $submit ) ) {
			return $this->exit( $out, $this->exit_message( $form_result, $row_inserted, false ) );
		}

		$submit_valid = [];

		foreach ( $submit as $email ) {
			if ( Sanitizer::validateEmail( $email ) ) {
				$submit_valid[] = $email;
			}
		}

		if ( !count( $submit_valid ) ) {
			return $this->exit( $out, $this->exit_message( $form_result, $row_inserted, false ) );
		}

		$subject = $this->msg( 'ci-forms-email-subject', $form_result['form_values']['title'] );
		$message_body =
			$this->msg( 'ci-forms-email-content', $form_result['form_values']['title'],
				$form_result['form_values']['pagename'] );
		$attachment = $this->createPDF( $form_result );

		// https://github.com/PHPMailer/PHPMailer/blob/master/examples/sendmail.phps

		// Create a new PHPMailer instance
		$mail = new PHPMailer();

		// Set PHPMailer to use the sendmail transport
		$mail->isSendmail();
		$mail->setFrom( $wgCIFormsSenderEmail );

		foreach ( $submit_valid as $key => $email ) {
			$mail->addAddress( $email );
		}

		$mail->Subject = $subject;
		$mail->msgHTML( $message_body );
		$mail->AltBody = $message_body;
		// $mail->addAttachment($attachment);
		$mail->AddStringAttachment( $attachment, $subject, "base64", "application/pdf" );

		$result_success = $mail->send();
		// echo $mail->ErrorInfo;
		$message = null;

		if ( !$result_success ) {
			return $this->exit( $out, $this->exit_message( $form_result, $row_inserted, true ) );
		} else {
			global $wgCIFormsSuccessMessage;

			if ( !empty( $form_result['form_values']['success-message'] ) ) {
				$message = $form_result['form_values']['success-message'];
			} else {
				if ( !empty( $wgCIFormsSuccessMessage ) ) {
					$message = $wgCIFormsSuccessMessage;
				} else {
					$message = $this->msg( 'ci-forms-dispatch-success' );
				}
			}
		}
		return $this->exit( $out, $message );
	}

	/**
	 * @param array $form_result
	 * @return bool
	 */
	private function storeSubmission( $form_result ) {
		$update_obj = [
			'title' => $form_result['form_values']['title'],
			'page_id' => $form_result['form_values']['pageid'],
			'data' => json_encode( $form_result ),
			'created_at' => date( 'Y-m-d H:i:s' )
		];

		$dbr = wfGetDB( DB_MASTER );

		$row_inserted = $dbr->insert(
			'CIForms_submissions',
			$update_obj
		);

		// store submissions groups
		if ( !empty( $form_result['form_values']['submission-groups'] ) ) {
			$groups = preg_split( "/[\s,]+/", $form_result['form_values']['submission-groups'] );

			if ( ( $key = array_search( '*', $groups ) ) !== false ) {
				// @phan-suppress-next-line PhanUndeclaredVariableDim
				$user_groups[$key] = 'all';
			}

			if ( in_array( 'all', $groups ) ) {
				$groups = [ 'all' ];
			}

			// a sysop can access all data, so we don't save usergroups related
			// to the submissions
			if ( $groups !== [ 'sysop' ] ) {
				$latest_id = $dbr->selectField(
					'CIForms_submissions',
					'id',
					[],
					__METHOD__,
					[ 'ORDER BY' => 'id DESC' ]
				);

				foreach ( $groups as $value ) {
					$row_inserted_ = $dbr->insert(
						'CIForms_submissions_groups',
						[
							'submission_id' => $latest_id,
							'usergroup' => $value,
							'created_at' => date( 'Y-m-d H:i:s' )
						]
					);
				}
			}
		}
		return $row_inserted;
	}

	/**
	 * @param array $form_result
	 * @param bool $row_inserted
	 * @param bool $dispatch
	 * @return Message
	 */
	protected function exit_message( $form_result, $row_inserted, $dispatch ) {
		global $wgCIFormsSenderEmail;
		global $wgCIFormsErrorMessage;

		if ( $row_inserted ) {
			return $this->msg( 'ci-forms-data-saved' );
		}

		if ( !empty( $form_result['form_values']['error-message'] ) ) {
			return $form_result['form_values']['error-message'];
		}

		if ( !empty( $wgCIFormsErrorMessage ) ) {
			return $wgCIFormsErrorMessage;
		}

		if ( !$dispatch ) {
			if ( !empty( $wgCIFormsSenderEmail ) ) {
				return $this->msg( 'ci-forms-data-not-saved-contact', $wgCIFormsSenderEmail );
			}
			return $this->msg( 'ci-forms-data-not-saved' );
		}

		if ( !empty( $wgCIFormsSenderEmail ) ) {
			return $this->msg( 'ci-forms-dispatch-error-contact', $wgCIFormsSenderEmail );
		}
		return $this->msg( 'ci-forms-dispatch-error' );
	}

	/**
	 * @param array $form_result
	 * @return string
	 */
	public function createPDF( $form_result ) {
		$css_path = __DIR__ . '/../../resources/style.css';

		$form_output_html = '';
		$form_output_html .= '<html><head>';
		$form_output_html .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
		// $form_output_html .= '<link rel="stylesheet" type="text/css" href="' . $css_url . '" />';

		$form_output_html .= '<style>';
		$stylesheet = file_get_contents( $css_path );

		// ***ensure there aren't spaces between brackets otherwise
		// Dompdf will not work
		$stylesheet = preg_replace( '/\[\s*(.+?)\s*\]/', "[$1]", $stylesheet );

		$form_output_html .= $stylesheet;

		// https://github.com/dompdf/dompdf/issues/708
		$form_output_html .= '.ci_form ol li::before, .ci_form ul li::before { content: ""; }';
		$form_output_html .= '</style>';
		$form_output_html .= '<head><body>';
		$form_output_html .= $this->create_output(
			$form_result['form_values'],
			$form_result['sections']
		);
		$form_output_html .= '<br /><br /><br /><br /><br />';
		$form_output_html .= $this->msg( 'ci-forms-credits' );
		$form_output_html .= '</body></html>';

		// create pdf
		// https://github.com/dompdf/dompdf

		// instantiate and use the dompdf class
		$dompdf = new Dompdf();
		$dompdf->loadHtml( $form_output_html );

		// (Optional) Setup the paper size and orientation
		$dompdf->setPaper( 'A4' );

		// Render the HTML as PDF
		$dompdf->render();

		// Output the generated PDF to Browser
		// $dompdf->stream();
		$file = $dompdf->output();

		return $file;
	}

	/**
	 * @param OutputPage $out
	 * @param string $message
	 */
	protected function exit( $out, $message ): void {
		$html = '<p>' . $message . '</p>';
		$out->addHTML( $html );
	}

	/**
	 * @param array $post
	 * @return array|bool[]
	 */
	protected function check_captcha( $post ) {
		global $wgCIFormsGoogleRecaptchaSecret;

		if ( empty( $wgCIFormsGoogleRecaptchaSecret ) ) {
			return [ false, 'ci-forms-google-recaptcha-secret-not-set' ];
		}

		if ( empty( $post['g-recaptcha-response'] ) ) {
			return [ false, 'ci-forms-recaptcha-challenge-not-found' ];
		}

		$captcha = $post['g-recaptcha-response'];

		$response =
			file_get_contents( "https://www.google.com/recaptcha/api/siteverify?secret=" .
				$wgCIFormsGoogleRecaptchaSecret . "&response=" . $captcha . "&remoteip=" .
				$_SERVER['REMOTE_ADDR'] );

		// use json_decode to extract json response
		$response = json_decode( $response, true );

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		if ( $response['success'] === false ) {
			// @phan-suppress-next-next-line PhanTypeArraySuspiciousNullable
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			return [ false, 'ci-forms-recaptcha-error', @$response['error-codes'][0] ];
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		if ( $response['success'] == true && $response['score'] <= 0.5 ) {
			return [ false, 'ci-forms-recaptcha-negative-score' ];
		}
		return [ true ];
	}

	/**
	 * @param array $post
	 * @return array
	 */
	protected function parseForm( $post ) {
		$props = [];
		$labels = [];
		// $values = [];
		$inputs = [];
		$form_values = [];
		$exclude = [ 'form', 'g-recaptcha-response', 'radio-for-required-checkboxes' ];

		foreach ( $post as $i => $value ) {
			$value = trim( $value );

			list( $section, $a, $b, $c, $d ) =
				// @phan-suppress-next-line PhanSuspiciousBinaryAddLists
				explode( '_', $i ) + [ null, null, null, null, null ];

			if ( in_array( $section, $exclude ) ) {
				if ( $section == 'form' ) {
					$form_values[$a] = $value;
				}
				continue;
			}

			if ( !array_key_exists( $section, $props ) ) {
				$props[$section] = [];
				$labels[$section] = [];
				// @phan-suppress-next-line PhanUndeclaredVariableDim
				$selected[$section] = [];
				$inputs[$section] = [];
			}

			switch ( $a ) {
				case 'section':
					$props[$section][$b] = $value;
					break;
				case 'items':
					if ( $c === 'label' ) {
						$labels[$section][$b] = $value;
					}
					// checkboxes
					if ( $c === 'selected' && $value !== '' ) {
						$selected[$section][$b] = true;
					}
					// radio, inputs unique name
					if ( $b === 'selected' && $value !== '' ) {
						$selected[$section][$value] = true;
					}
					if ( $c === 'input' ) {
						if ( !array_key_exists( $b, $inputs[$section] ) ) {
							$inputs[$section][$b] = [];
						}
						$inputs[$section][$b][$d] = $value;
					}
					break;
			}
		}

		$sections = [];

		foreach ( $props as $section => $value_ ) {
			$obj = array_merge( $value_, [ 'items' => [] ] );

			foreach ( $labels[$section] as $i => $row ) {
				$obj['items'][$i] = [
					'label' => $row,
					'selected' => ( !empty( $selected[$section][$i] ) ? $selected[$section][$i]
						: null ),
					'inputs' => ( !empty( $inputs[$section][$i] ) ? $inputs[$section][$i] : null ),
				];
			}
			$sections[] = $obj;
		}
		return [ 'form_values' => $form_values, 'sections' => $sections ];
	}

	/**
	 * @param string[] $form_values
	 * @param array[] $sections
	 * @return string
	 */
	protected function create_output( $form_values, $sections ) {
		$output = '<div class="ci_form pdf" style="max-width:none;background:none">';
		$output .= '<div class="ci_form_container">';

		if ( !empty( $form_values['title'] ) ) {
			$output .= '<div class="ci_form_title">';
			$output .= $form_values['title'];
			$output .= '</div>';
		}

		$output .= '<div class="ci_form_sections_container' .
			( count( $sections ) > 1 ? ' multiple_sections' : '' ) . '">';

		foreach ( $sections as $key => $section ) {
			$output .= '<div class="ci_form_section ' .
				htmlspecialchars( str_replace( ' ', '_', $section['type'] ) ) . '">';

			if ( !empty( $section['title'] ) ) {
				$output .= '<div class="ci_form_section_title">';
				$output .= $section['title'];
				$output .= '</div>';
			}

			switch ( $section['type'] ) {
				case 'inputs':
				case 'inputs responsive':
					// @todo make uniform to the function "ci_form_section_process"
					foreach ( $section['items'] as $value ) {
						$required = false;
						$placeholder = null;
						$label =
							trim( preg_replace_callback( '/\[\s*([^\[\]]*)\s*\]\s*(\*)?/',
								static function ( $matches ) use ( &$required, &$placeholder ) {
									$required = !empty( $matches[2] );

									list( $input_type, $placeholder, $input_options ) =
										CIForms::ci_form_parse_input_symbol( $matches[1] );
									return '';
								}, $value['label'] ) );

						if ( empty( $label ) ) {
							$label = $placeholder;
						}

						$output .= '<div class="ci_form_section_inputs_row">';
						$output .= '<div class="ci_form_section_inputs_col' .
							( $section['type'] == 'inputs responsive' ? '-25' : '' ) . '">';

						if ( !empty( $label ) ) {
							$output .= '<label>' . $label . ( $required ? ' *' : '' ) . '</label>';
						}

						if ( $section['type'] == 'inputs responsive' ) {
							$output .= '</div>';
							$output .= '<div class="ci_form_section_inputs_col-75">';
						}

						// $output .= '<input disabled="disabled" type="text" value="' .
						// htmlspecialchars($value['value']) . '" />';
						$output .= '<span class="input">' .
							htmlspecialchars( $value['inputs'][0] ) . '</span>';
						$output .= '</div>';
						$output .= '</div>';
					}
					break;
				case 'multiple choice':
					$list_type_ordered = in_array( $section['list-style'], CIForms::$ordered_styles );
					// --list_style_type
					$output .= '<' . ( !$list_type_ordered ? 'ul' : 'ol' ) . ' class="ci_form_section_multiple_choice_list" style="list-style:' . $section['list-style'] . '">';

					foreach ( $section['items'] as $value ) {
						$label = $value['label'];
						$ii = -1;
						$output .= '<li>';
						// @see https://stackoverflow.com/questions/35200674/special-character-not-showing-in-html2pdf
						$output .= '<span style="font-family:DejaVu Sans">' .
							( $value['selected'] ? '&#9745;' : '&#9744;' ) . '</span>&nbsp;';
						$label =
							preg_replace_callback( '/\[([^\[\]]*)\]\s*\*?/',
								static function ( $matches ) use ( $value, &$ii ) {
									$ii++;
									return '<span class="input">' .
										htmlspecialchars( $value['inputs'][$ii] ) . '</span>';
								}, $label );
						$output .= $label;
						$output .= '</li>';
					}
					$output .= ( $list_type_ordered ? '</ol>' : '</ul>' );
					break;
				case 'cloze test':
					$output .= '<ol class="ci_form_section_cloze_test_list">';
					$list_type_ordered = in_array( $section['list-style'], CIForms::$ordered_styles );
					// --list_style_type
					$output .= '<' . ( !$list_type_ordered ? 'ul' : 'ol' ) . ' class="ci_form_section_cloze_test_list" style="list-style:' . $section['list-style'] . '">';

					foreach ( $section['items'] as $value ) {
						$label = trim( $value['label'] );
						$example = ( $label[0] == '*' );

						if ( $example ) {
							$label = trim( substr( $label, 1 ) );
						}

						$output .= '<li class="ci_form_section_cloze_test_list_question' .
							( $example ? '_example' : '' ) . '">';
						$i = 0;
						$output .= preg_replace_callback( '/\[\s*([^\[\]]*)\s*\]\s*\*?/',
							static function ( $matches ) use ( &$i, $value, $section, $example ) {
									$a = $b = null;
								if ( !empty( $matches[1] ) ) {
									list( $a, $b ) = preg_split( "/\s*=\s*/", $matches[1] ) + [ null, null ];
								}
								$replacement_inner = '';

								if ( $a || $b ) {
									$replacement_inner .= '<span class="ci_form_section_cloze_test_list_question_answered">' .
										( $b ?: $a ) .
										'</span> ';
								} else {
									// '_value' is appended for easy validation
									$replacement_inner .= '<span class="input">' .
										htmlspecialchars( $value['inputs'][$i] ) . '</span> ';
								}
								$i++;
								return $replacement_inner;
							}, $label );
						$output .= '</li>';
					}
					$output .= '</ol>';
					break;
			}
			$output .= '</div>';
		}

		$output .= '</div>';
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

}
