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

class CIForms {
	/** @var bool */
	protected static $loadModule = false;

	/** @var array */
	public static $ordered_styles = [
		'decimal',
		'decimal-leading-zero',
		'lower-roman',
		'upper-roman',
		'lower-greek',
		'lower-latin',
		'upper-latin',
		'armenian',
		'georgian',
		'lower-alpha',
		'upper-alpha',
	];

	/**
	 * @param array $credits
	 */
	public static function initExtension( $credits = [] ) {
		if ( !defined( 'CIFORMS_VALUE_IF_NULL' ) ) {
			define( 'CIFORMS_VALUE_IF_NULL', 0 );
		}
		if ( !defined( 'CIFORMS_VALUE_OVERRIDE' ) ) {
			define( 'CIFORMS_VALUE_OVERRIDE', 1 );
		}
		if ( !defined( 'CIFORMS_VALUE_APPEND' ) ) {
			define( 'CIFORMS_VALUE_APPEND', 2 );
		}
	}

	/**
	 * Register any render callbacks with the parser
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'ci_form', [ self::class, 'ci_form' ] );
		$parser->setFunctionHook( 'ci_form_section', [ self::class, 'ci_form_section' ] );
	}

	/**
	 * @param OutputPage $outputPage
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $outputPage, Skin $skin ) {
		$outputPage->addJsConfigVars( [
			'ci-forms-validation-msg1' => wfMessage( 'ci-forms-validation-msg1' )->text(),
			'ci-forms-validation-msg2' => wfMessage( 'ci-forms-validation-msg2' )->text(),
			'ci-forms-validation-msg3' => wfMessage( 'ci-forms-validation-msg3' )->text(),
		] );

		$title = $outputPage->getTitle();
		$categories = $title->getParentCategories();

		// if( self::$loadModule ) {
		if ( array_key_exists( 'Category:Pages_with_forms', $categories ) ) {
			global $wgCIFormsGoogleRecaptchaSiteKey;
			global $wgResourceBasePath;

			$outputPage->addModules( 'ext.CIForms.validation' );

			if ( self::isCaptchaEnabled() ) {
				$outputPage->addJsConfigVars( [
					'ci_forms_google_recaptcha_site_key' => $wgCIFormsGoogleRecaptchaSiteKey
				] );
				$outputPage->addHeadItem( 'captcha_style',
					'<style>.grecaptcha-badge { visibility: hidden; display: none; }</style>' );
			}

			$items = [
				[ 'stylesheet', $wgResourceBasePath . '/extensions/CIForms/resources/style.css' ],
			];

			foreach ( $items as $key => $val ) {
				list( $type, $url ) = $val;

				switch ( $type ) {
					case 'stylesheet':
						$item = '<link rel="stylesheet" href="' . $url . '" />';
						break;
					case 'script':
						$item = '<script src="' . $url . '"></script>';
						break;

				}
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
				$outputPage->addHeadItem( 'CI_head_item_' . $key, $item );
			}
		}
	}

	/**
	 * @param DatabaseUpdater|null $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater = null ) {
		$base = __DIR__;

		$dbType = $updater->getDB()->getType();

		$array = [
			[
				'table' => 'CIForms_submissions',
				'filename' => '../' . $dbType . '/CIForms_submissions.sql'
			],
			[
				'table' => 'CIForms_submissions_groups',
				'filename' => '../' . $dbType . '/CIForms_submissions_groups.sql'
			],
		];

		foreach ( $array as $value ) {
			if ( file_exists( $base . '/' . $value['filename'] ) ) {

				$updater->addExtensionUpdate(
					[
						'addTable', $value['table'],
						$base . '/' . $value['filename'], true
					]
				);
			}
		}
	}

	/**
	 * @return bool
	 */
	public static function isCaptchaEnabled() {
		global $wgCIFormsGoogleRecaptchaSiteKey;
		global $wgCIFormsGoogleRecaptchaSecret;

		return ( !empty( $wgCIFormsGoogleRecaptchaSiteKey ) && !empty( $wgCIFormsGoogleRecaptchaSecret ) );
	}

	/**
	 * @param Parser $parser
	 * @param mixed ...$argv
	 * @return array
	 */
	public static function ci_form( Parser $parser, ...$argv ) {
		self::$loadModule = true;

		$named_parameters = [
			'submit' => null,	// legacy
			'email to' => null,
			'title' => null,
			'submission groups' => null,	// legacy
			'data access' => null,
			'success message' => null,
			'error message' => null,
			'success page' => null,
			'error page' => null,
			'paging' => null,	// true, or 1,1,1,1,1, or 2,2,1
			'submit text' => null,
			'navigation next' => null,
			'navigation back' => null,
			'css class' => '',

		]; // email to which submit

		$parser->addTrackingCategory( 'ci-form' );
		$set_named_parameters = [];
		$body = self::parse_function_arguments( $argv, $named_parameters, $set_named_parameters );

		$subsections = [];
		foreach ( $body as $key => $value ) {
			// https://www.mediawiki.org/wiki/Strip_marker
			// @todo add some logic to fix a missing pipe

			if ( preg_match( '/^(\x7F\'"`UNIQ.+?QINU`"\'\x7F){1}(\s+\x7F\'"`UNIQ.+?QINU`"\'\x7F)*$/',
				$value ) ) {

				// *** handle missing pipes between nested sections
				preg_match_all( '/\x7F\'"`UNIQ.+?QINU`"\'\x7F/', $value, $match );
				$subsections = array_merge( $subsections, $match[0] );
				unset( $body[$key] );
			}
		}

		$output = '';
		$url = Title::newFromText( 'Special:CIFormsSubmit' )->getLocalURL();

		$output .= '<form class="ci_form' . ( !empty( $named_parameters['css class'] ) ? " " .
				htmlspecialchars( $named_parameters['css class'] ) : '' ) . '" action="' . $url .
			'" method="post">';
		$output .= '<div class="ci_form_container">';

		// allow wiki-text and html in titles
		if ( !empty( $named_parameters['title'] ) ) {
			$named_parameters['title'] =
				self::replace_wikitext_and_html( $named_parameters['title'] );
		}

		if ( !empty( $named_parameters['title'] ) ) {
			$output .= '<div class="ci_form_title">';
			$output .= self::replace_wikitext_and_html( $named_parameters['title'] );
			$output .= '</div>';
		}

		$output .= '<div class="ci_form_sections_container' .
			( count( $subsections ) ? ' multiple_sections' : '' ) . '">';

		if ( count( $body ) ) {
			$output .= self::ci_form_section_process( $body );
		}

		if ( count( $subsections ) ) {

			if ( !empty( $named_parameters['paging'] ) && $named_parameters['paging'] !== 'false' ) {

				// we cannot set the visibility in the section's container
				// itself, because the nested parser function is rendered
				// independently before the container

				// group according to paging
				// eg. 5 sections
				// true = 1, 1, 1, 1, 1
				// 2, 2, 1
				// 2, 2 = 2, 2, 1
				// 2, 3
				// 2, 4 = 2, 3
				// 6, 1 = 5

				if ( $named_parameters['paging'] === 'true' ) {
					$split = array_fill( 0, count( $subsections ), 1 );

				} else {
					$split = preg_split( "/\s*,\s*/", $named_parameters['paging'] );
				}

				$n = 0;

				foreach ( $split as $i => $value ) {
					$output .= '<div class="ci_form_section_display_' . $i . '" style="display:' . ( $i > 0 ? 'none' : 'block' ) . '">' . implode( array_slice( $subsections, $n, $value ) ) . '</div>';
					$n += $value;
				}

			} else {
				$output .= implode( $subsections );
			}

		}

		$output .= '</div>';
		$output .= '<div class="ci_form_section_submit">';

		$hidden_fields = [ 'title', 'submission groups', 'data access', 'submit', 'email to', 'success message', 'error message', 'paging', 'success page', 'error page' ];

		foreach ( $hidden_fields as $value ) {
			$output .= self::hidden_input( 'form_' . str_replace( ' ', '-', $value ), $named_parameters[$value] );
		}

		if ( self::isCaptchaEnabled() ) {
			$output .= '<input type="hidden" name="g-recaptcha-response">';
		}

		$title = $parser->getTitle();

		$output .= self::hidden_input( 'form_pagename', $title->getText() );
		$output .= self::hidden_input( 'form_pageid', $title->getArticleID() );

		if ( empty( $named_parameters['paging'] ) || $named_parameters['paging'] === 'false' ) {
			$output .= '<input class="ci_form_input_submit" type="submit" value="' . ( !empty( $named_parameters['submit text'] ) ? htmlspecialchars( $named_parameters['submit text'] ) : wfMessage( 'ci-forms-submit' )->text() ) . '">';

		} else {
			$output .= '<div style="text-align:right">';
			$output .= '<button style="display:none" type="button" class="ci_form_input_navigation_back">' . ( !empty( $named_parameters['navigation back'] ) ? htmlspecialchars( $named_parameters['navigation back'] ) : wfMessage( 'ci-forms-navigation-back' )->text() ) . '</button>';
			$output .= '<button class="ci_form_input_navigation_next">' . ( !empty( $named_parameters['navigation next'] ) ? htmlspecialchars( $named_parameters['navigation next'] ) : wfMessage( 'ci-forms-navigation-next' )->text() ) . '</button>';
			$output .= '<input style="display:none" class="ci_form_input_submit" type="submit" value="' . ( !empty( $named_parameters['submit text'] ) ? htmlspecialchars( $named_parameters['submit text'] ) : wfMessage( 'ci-forms-submit' )->text() ) . '">';
			$output .= '</div>';
		}

		$output .= '</div>';
		$output .= '</div>';
		$output .= '<div class="ci_form_section_captcha">';

		if ( self::isCaptchaEnabled() ) {
			$output .= 'form protected using <a target="_blank" style="color:silver;text-decoration:" href="https://www.google.com/recaptcha/about/">Google recaptcha</a>';
		}

		$output .= '</div>';
		$output .= '</form>';

		return [
			// @phan-suppress-next-line SecurityCheck-XSS
			$output,
			'noparse' => true,
			'isHTML' => true
		];
	}

	/**
	 * @param array $argv
	 * @param array &$named_parameters
	 * @param array &$set_named_parameters
	 * @return array
	 */
	protected static function parse_function_arguments(
		$argv,
		&$named_parameters,
		&$set_named_parameters
	) {
		$body = [];
		// $set_named_parameters = [];
		$unique_id = uniqid();

		foreach ( $argv as $value ) {
			$value = trim( $value );
			$value = preg_replace( '/\040+/', ' ', $value );

			if ( empty( $value ) ) {
				continue;
			}

			// square brackets may contain an equal symbol
			// so we temporarily remove it
			// $value_ = preg_replace('/\[\s*(.+?)\s*\]\s*\*?/','',$value);

			// replace html and square brackets with some identifier

			$replacements = [];
			$value_ =
				preg_replace_callback( '/(<[^<>]+>)|(\[[^\[\]]+\])/',
					static function ( $matches ) use ( &$replacements, $unique_id ) {
						if ( $matches[1] ) {
							$replacements[] = '<html>' . $matches[0] . '</html>';
						} else {
							$replacements[] = $matches[0];
						}
						return $unique_id;
					}, $value );

			if ( strpos( $value_, '=' ) !== false ) {
				list( $parameter_key, $parameter_value ) = explode( '=', $value_, 2 );
				$parameter_key = trim( str_replace( [ '_', '-' ], ' ', $parameter_key ) );

				if ( array_key_exists( $parameter_key, $named_parameters ) ) {
					$parameter_value =
						preg_replace_callback( '/' . $unique_id . '/',
							static function ( $matches ) use ( &$replacements, $unique_id ) {
								return array_shift( $replacements );
							},
							$parameter_value );
					$named_parameters[$parameter_key] = trim( $parameter_value );
					$set_named_parameters[] = $parameter_key;
					continue;
				}
			}
			$body[] = $value;
		}
		return $body;
	}

	/**
	 * @param string $value
	 * @return array|string|string[]|null
	 */
	protected static function replace_wikitext_and_html( $value ) {
		$context = new RequestContext();
		$out_ = new OutputPage( $context );
		$unique_id = uniqid();
		$replacements = [];
		$value =
			preg_replace_callback( '/<[^<>]+>/',
				static function ( $matches ) use ( &$replacements, $unique_id ) {
					$replacements[] = $matches[0];
					return $unique_id;
				}, $value );

		$value = Parser::stripOuterParagraph( $out_->parseAsContent( $value ) );
		$value =
			preg_replace_callback( '/' . $unique_id . '/',
				static function ( $matches ) use ( &$replacements, $unique_id ) {
					return array_shift( $replacements );
				}, $value );
		return $value;
	}

	/**
	 * @param string $name
	 * @param string|int|null $value
	 * @return string $value
	 */
	protected static function hidden_input( $name, $value ) {
		return '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars( (string)$value ) . '">';
	}

	/**
	 * @param array|string[] $argv
	 * @return string
	 */
	protected static function ci_form_section_process( $argv ) {
		$output = '';

		// default values
		$named_parameters = [
			'type' => 'inputs',
			// 'inputs', 'inputs resposive', 'multiple choice', 'cloze', 'cloze-test'
			'title' => null,
			'list type' => 'none',
			// 'unordered', 'letters', 'numbers' + standard values
			'max answers' => 1,
			'min answers' => null,	// number or percent, for multiple choice questions if max answers > 1, default 1/2 +1
			'suggestions' => null
			// if multiple choice
		];
		$set_named_parameters = [];
		$body = self::parse_function_arguments( $argv, $named_parameters, $set_named_parameters );

		// alias
		if ( $named_parameters['type'] == 'cloze' ) {
			$named_parameters['type'] = 'cloze test';
		}

		// alias
		if ( $named_parameters['type'] == 'input' ) {
			$named_parameters['type'] = 'inputs';
		}

		// cloze test list type default value
		if ( !in_array( 'list type', $set_named_parameters ) &&
			$named_parameters['type'] == 'cloze test' ) {
			$named_parameters['list type'] = 'ordered';
		}

		$unique_id = uniqid();
		$output .= '<div class="ci_form_section ' .
			htmlspecialchars( str_replace( ' ', '_', $named_parameters['type'] ) ) . '" data-id="' .
			$unique_id . '">';

		switch ( $named_parameters['type'] ) {
			case 'cloze test':
			case 'multiple choice':
				if ( in_array( $named_parameters['list type'], self::$ordered_styles ) ) {
					$list_style = $named_parameters['list type'];
				} else {
					switch ( $named_parameters['list type'] ) {
						case 'letters':
							$list_style = 'upper-latin';
							break;
						case 'ordered':
						case 'numbers':
							$list_style = 'decimal';
							break;
						case 'unordered':
							$list_style = 'disc';
							break;
						default:
							$list_style = 'none';

					}
				}

				$output .= self::hidden_input( $unique_id . '_section_list-style', $list_style );

				if ( $named_parameters['type'] == 'multiple choice' ) {
					$output .= self::hidden_input( $unique_id . '_section_multiple-choice-max-answers', $named_parameters['max answers'] );
				}

				if ( (int)$named_parameters['max answers'] > 1 || $named_parameters['type'] == 'cloze test' ) {
					$output .= self::hidden_input( $unique_id . '_section_multiple-choice-min-answers', $named_parameters['min answers'] );
				}
				break;
			case 'inputs':
			case 'inputs responsive':
				break;
		}

		// allow wiki-text and html in titles
		if ( !empty( $named_parameters['title'] ) ) {
			$named_parameters['title'] =
				self::replace_wikitext_and_html( $named_parameters['title'] );
		}

		$output .= self::hidden_input( $unique_id . '_section_type', $named_parameters['type'] );
		$output .= self::hidden_input( $unique_id . '_section_title', $named_parameters['title'] );

		if ( !empty( $named_parameters['title'] ) ) {
			$output .= '<div class="ci_form_section_title">' . $named_parameters['title'] . '</div>';
		}

		switch ( $named_parameters['type'] ) {
			case 'inputs':
			case 'inputs responsive':
				$n = 0;
				foreach ( $body as $value ) {
					$output .= '<div class="ci_form_section_inputs_row">';
					$output .= '<div class="ci_form_section_inputs_col' .
						( $named_parameters['type'] == 'inputs responsive' ? '-25' : '' ) . '">';

					$output .= self::hidden_input( $unique_id . '_items_' . $n . '_label', $value );

					preg_match_all( '/([^\[\]]*)\[\s*([^\[\]]*)\s*\]\s*(\*)?/', $value, $match_all );
					$inputs_per_row = count( $match_all[0] );

					$i = 0;
					$output .= preg_replace_callback( '/([^\[\]]*)\[\s*([^\[\]]*)\s*\]\s*(\*)?/',
						function ( $matches ) use ( $named_parameters, &$i, $n, $unique_id, $inputs_per_row ) {
							$replacement = '';

							if ( $inputs_per_row > 1 ) {
								$replacement .= '<div class="ci_form_section_inputs_inner_col" style="float:left;width:' . ( 100 / $inputs_per_row ) . '%">';
							}

							$label = trim( $matches[1] );

							list( $input_type, $placeholder, $input_options ) =
								self::ci_form_parse_input_symbol( $matches[2] ) + [ null, null, null ];

							$required =
								( !empty( $matches[3] ) ? ' data-required="1"' : '' );

							// @phan-suppress-next-line PhanRedundantCondition
							if ( $required && !empty( $placeholder ) ) {
								$placeholder .= ' *';
							}

							if ( !empty( $label ) ) {
								$replacement .= '<label>' . $label .
									( $required && empty( $placeholder ) ? ' *' : '' ) . '</label>';
							}

							if ( $named_parameters['type'] == 'inputs responsive' && $i == 0 ) {
								$replacement .= '</div>';
								$replacement .= '<div class="ci_form_section_inputs_col-75">';
							}

							switch ( $input_type ) {
								case 'textarea':
									// '_value' is appended for easy validation
									$replacement .= '<textarea rows="4" name="' . $unique_id .
										'_items_' . $n . '_input_' . $i . '_value"' .
										( $input_options && is_numeric( $input_options ) ? ' maxlength="' . $input_options . '"' : '' ) .
										( $placeholder ? ' placeholder="' .
											htmlspecialchars( $placeholder ) . '"' : '' ) .
										$required . '></textarea>' .
										( $input_options && is_numeric( $input_options ) ? '<span class="ci_form_section_inputs_textarea_maxlength">0/' . $input_options . ' characters</span>' : '' );
									break;

								case 'select':
									// *** this could be replaced with any symbol
									// not allowed in the input descriptor

									// $placeholder can be: a,b, c, or a:x,b:y, c:z, or: a:x\, a, b:y (comma
									// can be escaped

									$id_tmp = uniqid();

									$select_options = str_replace( '\\,', $id_tmp, $input_options );
									$select_options = preg_split( "/\s*,\s*/", $select_options );

									$replacement .= '<select name="' . $unique_id . '_items_' . $n .
										'_input_' . $i . '_value" type="' . $input_type . '"' .
										( $placeholder ? ' placeholder="' .
											htmlspecialchars( $placeholder ) . '"' : '' ) .
										$required . '>';

									if ( $placeholder ) {
										$replacement .= '<option value="" disabled selected>' . $placeholder . '</option>';
									}

									$replacement .= implode( array_map(
										static function ( $val ) use ( $id_tmp ) {
											$val = str_replace( $id_tmp, ',', $val );

											// replace with Zero-width space
											// to ensure select2 renders the character properly
											if ( empty( $val ) ) {
												$val = ':&#8203;';
											}

											if ( strpos( $val, ':' ) === false ) {
												return '<option>' . $val . '</option>';
											}

											list( $value, $label ) = preg_split( "/\s*:\s*/", $val );

											return '<option value="' . htmlspecialchars( $value ) . '">' . $label . '</option>';
										}, $select_options ) );

									$replacement .= '</select>';
									break;

								default:
								case 'text':
								case 'email':
									// '_value' is appended for easy validation
									$replacement .= '<input name="' . $unique_id . '_items_' . $n .
										'_input_' . $i . '_value" type="' . $input_type . '"' .
										( $placeholder ? ' placeholder="' .
											htmlspecialchars( $placeholder ) . '"' : '' ) .
										$required . '/>';
									break;
							}

							if ( $inputs_per_row > 1 ) {
								$replacement .= '</div>';
							}

							$i++;
							return $replacement;
						}, $value ); // preg_replace_callback

					$output .= '</div>';
					$output .= '</div>';
					$n++;
				}
				break;
			case 'multiple choice':
				$list_type_ordered = in_array( $list_style, self::$ordered_styles );

				// https://stackoverflow.com/questions/23699128/how-can-i-reset-a-css-counter-to-the-start-attribute-of-the-given-list

				$output .= '<' . ( !$list_type_ordered ? 'ul' : 'ol' ) . ' class="ci_form_section_multiple_choice_list" style="--list_style_type:' . $list_style . '">';
				$n = 0;

				// native validation, see the following:

				// https://stackoverflow.com/questions/8287779/how-to-use-the-required-attribute-with-a-radio-input-field
				// https://stackoverflow.com/questions/6218494/using-the-html5-required-attribute-for-a-group-of-checkboxes

				$output .= ( $named_parameters['max answers'] > 1
					? '<input class="radio_for_required_checkboxes" type="radio" name="' .
					uniqid() . '" data-required="1" />' : '' );

				foreach ( $body as $key => $value ) {
					$output .= '<li>';
					$output .= self::hidden_input( $unique_id . '_items_' . $n . '_label', $value );

					// if it's a radio, the input name shall be the same for all inputs
					$output .= '<input name="' . $unique_id . '_items_' .
						( $named_parameters['max answers'] > 1 ? $n . '_' : '' ) .
						'selected" type="' .
						( $named_parameters['max answers'] == 1 ? 'radio' : 'checkbox' ) .
						'" value="' . $n . '"' .
						( $named_parameters['max answers'] == 1 ? ' data-required="1"' : '' ) . ' />';

					$i = 0;

					$output .= preg_replace_callback( '/\[([^\[\]]*)\]/',
						static function ( $matches ) use ( &$i, $n, $unique_id ) {
							$replacement =
								'<input name="' . $unique_id . '_items_' . $n . '_input_' . $i .
								'" type="text" data-required="1" />';
							$i++;
							return $replacement;
						}, $value ); // preg_replace_callback
					$output .= '</li>';
					$n++;
				}
				$output .= ( $list_type_ordered ? '</ol>' : '</ul>' );
				break;
			case 'cloze test':
				$suggestions = [];
				if ( !empty( $named_parameters['suggestions'] ) ) {
					$suggestions = preg_split( "/\s*,\s*/", $named_parameters['suggestions'] );
				}

				$items = [];
				$answers = [];

				foreach ( $body as $key => $value ) {
					$example = false;

					$value = trim( $value );
					$value = preg_replace( '/\s+/', ' ', $value );

					// @todo in a cloze test the asterisk used to mark an example is redundant

					if ( $value[0] == '*' ) {
						$example = true;
						$value = trim( substr( $value, 1 ) );
					}

					preg_match_all( '/\[\s*([^\[\]]*)\s*\]/', $value, $matches );

					$inputs = [];

					if ( !empty( $matches[0] ) ) {
						foreach ( $matches[0] as $i => $match ) {
							$a = $b = null;

							if ( $matches[1][$i] ) {

								// the suggestions could be "transformed" regard
								// a possible answer, for instance:
								// suggestions: "to be, to do, to make"
								// example answer in past perfect
								// I [to be=was] proud to win ...

								list( $a, $b ) = preg_split( "/\s*=\s*/", $matches[1][$i] ) + [ null, null ];

							}

							$found_suggestion = preg_grep( '/^' . preg_quote( $a ) . '$/i', $suggestions );

							if ( count( $found_suggestion ) ) {
								$answers[] = array_shift( $found_suggestion );
							}

							$inputs[] = [ $a, $b ];

						}
					}
					$items[] = [ $value, $example, $inputs ];
				}

				shuffle( $suggestions );

				$output .= self::hidden_input( $unique_id .	'_section_cloze-test-suggestions', implode( ',', $suggestions ) );
				$output .= self::hidden_input( $unique_id .	'_section_cloze-test-answers', implode( ',', $answers ) );

				// suggestions framed
				if ( !empty( $suggestions ) ) {
					$output .= '<div class="ci_form_section_cloze_test_suggestions">';

					foreach ( $suggestions as $word ) {
						$output .= '<span class="ci_form_section_cloze_test_suggestions_word' .
							( in_array( $word, $answers ) ? '_answered' : '' ) . '">';
						$output .= $word;
						$output .= '</span>';

						if ( in_array( $word, $answers ) ) {
							$key = array_search( $word, $answers );
							unset( $answers[$key] );
						}
					}
					$output .= '</div>';
				}

				$list_type_ordered = in_array( $list_style, self::$ordered_styles );

				// https://stackoverflow.com/questions/23699128/how-can-i-reset-a-css-counter-to-the-start-attribute-of-the-given-list

				$output .= '<' . ( !$list_type_ordered ? 'ul' : 'ol' ) . ' class="ci_form_section_cloze_test_list" style="--list_style_type:' . $list_style . '">';
				$n = 0;

				foreach ( $items as $value ) {
					list( $label, $example, $inputs ) = $value;

					$output .= '<li class="ci_form_section_cloze_test_list_question' .
						( $example ? '_example' : '' ) . '">';

					$output .= self::hidden_input( $unique_id .	'_items_' . $n . '_label', ( $example ? '* ' : '' ) . $label );

					$i = 0;
					$label =
						preg_replace_callback( '/\[([^\[\]]*)\]/',
							static function ( $matches ) use ( &$i, &$inputs, $example, $n, $unique_id ) {
								list( $a, $b ) = array_shift( $inputs );
								$replacement = '';

								if ( $a || $b ) {
									$replacement .= '<span class="ci_form_section_cloze_test_list_question_answered">' .
										( $b ?: $a ) .
										'</span>';
								} else {
									// '_value' is appended for easy validation
									$replacement .= '<input name="' . $unique_id . '_items_' . $n .
										'_input_' . $i . '_value" type="text" />';
								}
								$i++;
								return $replacement;
							}, $label ); // preg_replace_callback

					$output .= $label;
					$output .= '</li>';
					$n++;
				}
				$output .= ( $list_type_ordered ? '</ol>' : '</ul>' );
				break;
		}
		$output .= '</div>';
		return $output;
	}

	/**
	 * @param string $value
	 * @return array
	 */
	public static function ci_form_parse_input_symbol( $value ) {
		if ( empty( $value ) ) {
			return [ 'text', null, null ];
		}

		$input_types = [
				'text',
				'password',
				'textarea',
				'email',
				'search',
				'tel',
				'file',
				'number',
				'url',
				'time',
				'date',
				'select',
			];

		// [first name]
		// [first name=text]
		// [email]
		// [email=email]
		// [select=a,b,c]
		// [Select option=select=a,b,c]
		// [textarea=500]
		// [enter=textarea=500]
		// [enter text=textarea=500]

		list( $a, $b, $c ) = preg_split( "/\s*=\s*/", $value ) + [ null, null, null ];

		if ( $b && $c ) {
			return [ $b, $a, $c ];
		}

		if ( in_array( $b, $input_types ) ) {
			return [ $b, $a, null ];
		}

		if ( in_array( $a, $input_types ) ) {
			return [ $a, null, $b ];
		}

		if ( !$b ) {
			$b = 'text';
		}

		return [ $b, $a, null ];
	}

	/**
	 * @param Parser $parser
	 * @param mixed ...$argv
	 * @return array
	 */
	public static function ci_form_section( Parser $parser, ...$argv ) {
		$output = self::ci_form_section_process( $argv );
		// @phan-suppress-next-line SecurityCheck-XSS
		return [ $output, 'noparse' => true, 'isHTML' => true ];
	}

}
