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
 * @copyright Copyright © 2021, https://culturaitaliana.org
 */




class CIForms {

	protected static $loadModule = false;


	// Register any render callbacks with the parser
	public static function ParserFirstCallInit( Parser $parser ) {
	
		$parser->setFunctionHook( 'ci form', [ self::class, 'ci_form' ] );	//,SFH_OBJECT_ARGS
		$parser->setFunctionHook( 'ci_form', [ self::class, 'ci_form' ] );	//,SFH_OBJECT_ARGS

		$parser->setFunctionHook( 'ci form section', [ self::class, 'ci_form_section' ] );
		$parser->setFunctionHook( 'ci_form_section', [ self::class, 'ci_form_section' ] );

	}




	public static function BeforePageDisplay( OutputPage $outputPage, Skin $skin ) {

		$title = $outputPage->getTitle();
		$categories = $title->getParentCategories();
		
		//if(self::$loadModule) {
		if(array_key_exists('Category:Pages_with_forms',$categories)) {

			global $wgCIFormsGoogleRecaptchaSiteKey;
			global $wgResourceBasePath;

			$outputPage->addJsConfigVars(['ci_forms_google_recaptcha_site_key' => $wgCIFormsGoogleRecaptchaSiteKey]);
			$outputPage->addModules( 'ext.CIForms.validation' );
			//$outputPage->addModuleStyles( 'ext.CIForms.validation' );
			$outputPage->addHeadItem('captcha_style', '<style>.grecaptcha-badge { visibility: hidden; display: none; }</style>' );

			$css = '<link rel="stylesheet" href="' . $wgResourceBasePath . '/extensions/CIForms/resources/style.css" />';

			$outputPage->addHeadItem('ci_forms_css', $css );

		}


	}



	public static function ci_form(Parser $parser,...$argv) {

		self::$loadModule = true;

		global $wgCIFormsSuccessMessage;
		global $wgCIFormsErrorMessage;
		global $wgCIFormsSubmitEmail;

		$named_parameters = [
			'submit' => $wgCIFormsSubmitEmail,
			'title' => null,
			'success message' => null,	//$wgCIFormsSuccessMessage,
			'error message' => null,	//$wgCIFormsErrorMessage

		 ]; // email to which submit


		$parser->addTrackingCategory( 'ci-form' );

		
		$lines = self::parse_function_arguments($named_parameters,$argv);

	
		
		$section_lines = [];


		foreach($lines as $key => $value) {

			// https://www.mediawiki.org/wiki/Strip_marker

	// *** to-do include a check when a pipe is missing

			if(!preg_match('/^(\x7F\'"`UNIQ.+?QINU`"\'\x7F){1}(\s+\x7F\'"`UNIQ.+?QINU`"\'\x7F)*$/',$value)) {
				unset($lines[$key]);
				$section_lines[] = $value;

			} else {
				$lines[$key] = preg_replace('/\s+/',"\n",$value);
			}

	
		}


		$output = '';




		$url = Title::newFromText('Special:CIFormsSubmit')->getLocalURL();


		$output .= '<form class="ci_form" action="' . $url . '" method="post">';
		$output .= '<div class="ci_form_container">';

		if(!empty($named_parameters['title'])) {
			$output .= '<div class="ci_form_title">';
			$output .= $named_parameters['title'];
			$output .= '</div>';
		}



		if($section_lines) {
			$output .= self::ci_form_section_process($section_lines);
		}


		if($lines) {
			$output .= implode($lines);
		}





		$output .= '<div class="ci_form_section_submit">';

		$output .= '<input type="hidden" name="form_title" value="' . htmlspecialchars($named_parameters['title']) . '">';
		$output .= '<input type="hidden" name="form_submit" value="' . htmlspecialchars($named_parameters['submit']) . '">';
		$output .= '<input type="hidden" name="form_success-message" value="' . htmlspecialchars($named_parameters['success message']) . '">';
		$output .= '<input type="hidden" name="form_error-message" value="' . htmlspecialchars($named_parameters['error message']) . '">';


		$output .= '<input type="hidden" name="g-recaptcha-response">';

		$title = $parser->getTitle();

		$output .= '<input type="hidden" name="form_pagename" value="' . htmlspecialchars($title->getText()) . '">';

		$output .= '<input class="ci_form_input_submit" type="submit" value="Submit">';


		$output .= '</div>';

		$output .= '</div>';
		$output .= '<div class="ci_form_section_captcha">';
		$output .= 'form protected using <a target="_blank" style="color:silver;text-decoration:" href="https://www.google.com/recaptcha/about/">Google recaptcha</a>';
		$output .= '</div>';
		$output .= '</form>';



		return array( $output, 'noparse' => true, 'isHTML' => true);

	}




	private static function ci_form_section_replace_input($named_parameters,$value,$unique_id,$n,&$required,$inner = false) {

	
		switch($named_parameters['type']) {

			case 'inputs': 

				preg_match('/\[.*?\]\s*\*?/',$value,$match);

				if(empty($match[0])) {
					return '';
				}

				$value = $match[0];

			break;


			case 'multiple choice' :


			break;

		}


		

		$i = 0;

		return preg_replace_callback('/\[\s*(.*?)\s*\]\s*(\*)?/', function($matches) use ($named_parameters,$value,$unique_id,$n,$inner,&$i,&$required) {

			$input_type = 'text';
			$placeholder = null;


		
			// for now we allow multiple inputs per line
			// only for multiple choice questions

			if($named_parameters['type'] != 'multiple choice' && $i > 0) {
				return $matches[0];
			}




			switch($named_parameters['type']) {

				case 'inputs' :

					// https://quasar.dev/vue-components/input
					// text password textarea email search tel file number url time date

					$input_types = ['text', 'password', 'textarea', 'email', 'search', 'tel', 'file', 'number', 'url', 'time', 'date'];


					if(!empty($matches[1])) {

						// [first name]
						// [first name=text]
						// [email]

						list($a,$b) = explode('=',$matches[1]) + array(null,null);
			
						if($b) {
							$input_type = $b;
							$placeholder = $a;

						} else {

							if(in_array($a,$input_types)) {
								$input_type = $a;

							} else {
								$placeholder = $a;
							} 
					
						}

					}

					$required = (!empty($matches[2]) ? ' data-required="1" required' : '');

					if($required && empty($label) && !empty($placeholder)) {
						$placeholder .= ' *';
					}


				break;


				default: 
					$required = ' data-required="1"';
				


			}
	


			if(!$inner) {
				$name = $unique_id . '_items_' . $n . '_value';

			} else {

				// inputs inside multiple choice questions
				$name = $unique_id . '_items_' . $n . '_input_' . $i;
			}



			switch($input_type) {

				case 'textarea' :
					$replacement = '<textarea rows="4" name="' . $name . '"' . ($placeholder ? ' placeholder="' . htmlspecialchars($placeholder) . '"' : '') . $required . '></textarea>';

				break;

				default: 
				case 'text' :
				case 'email' :
  					$replacement = '<input name="' . $name . '" type="' . $input_type . '"' . ($placeholder ? ' placeholder="' . htmlspecialchars($placeholder) . '"' : '') . $required . '/>';
				break;

			}

			$i++;

			return $replacement;

		},$value);

	}


	private static function ci_form_section_replace_line($named_parameters,$value,$unique_id,$n,$label = null) {
		

		$i = 0;

		$required = false;


		switch($named_parameters['type']) {

			case 'inputs': 
				
				$input = self::ci_form_section_replace_input($named_parameters,$value,$unique_id,$n,$required);


				if($label) {
					
					return 
						'<td class="ci_form_section_table_cell_left">' 
						. '<input type="hidden" name="' . $unique_id . '_items_' . $n . '_label" value="' . htmlspecialchars($value) . '" />'
						. $label . ($required ? ' *' : '')
						. '<td class="ci_form_section_table_cell_right">'
						. $input
						. '</td>';

				} else {

					return 
						'<input type="hidden" name="' . $unique_id . '_items_' . $n . '_label" value="' . htmlspecialchars($value) . '" />'
						. $input;
					
				}

			break;
			
		
			case 'multiple choice' :

				// native validation, see the following:

				// https://stackoverflow.com/questions/8287779/how-to-use-the-required-attribute-with-a-radio-input-field
				// https://stackoverflow.com/questions/6218494/using-the-html5-required-attribute-for-a-group-of-checkboxes
	
				return 

				($named_parameters['max answers'] > 1 && $n == 0 ? '<input id="radio-for-checkboxes" type="radio" name="radio-for-required-checkboxes" required/>' : '') 

					. '<input type="hidden" name="' . $unique_id . '_items_' . $n . '_label" value="' . htmlspecialchars($value) . '" />'
					. '<input name="' . $unique_id . '_items_' . ($named_parameters['max answers'] > 1 ? $n . '_' : '') . 'value" type="' . ($named_parameters['max answers'] == 1 ? 'radio' : 'checkbox') .'" value="' . $n . '"' . ($named_parameters['max answers'] == 1 ? ' required' : '') . ' />'

					. self::ci_form_section_replace_input($named_parameters,$value,$unique_id,$n,$required,true);

			break;


		}
		

	}




	protected static function ci_form_section_process($argv) {

		$output = '';


		// default values
		$named_parameters = [
			'type' => 'inputs',		// 'inputs', 'multiple choice', 'cloze', 'cloze-test'
			'title' => null,
			'list-type' => 'none',	// 'unordered', 'letters', 'numbers' + standard values
			'max answers' => 1,
			'suggestions' => null	// if multiple choice
		];




		$lines = self::parse_function_arguments($named_parameters,$argv);


		$unique_id = uniqid();

		

		$output .= '<div class="ci_form_section ' . htmlspecialchars(str_replace(' ','_',$named_parameters['type'])) . '" data-id="' . $unique_id . '">';

	

		switch($named_parameters['type']) {

			case 'multiple choice': 

	
				switch($named_parameters['list-type']) {
					case 'letters' :
						$list_style = 'upper-latin';
					break;
					case 'ordered' :
					case 'numbers' :
						$list_style = 'decimal';
					break;
					case 'unordered' :
						$list_style = 'circle';
					break;
			
					default:
						$list_style = 'none';

				}

				$ordered_styles = [
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
					'upper-alpha'
				];



				$output .= '<input type="hidden" name="' . $unique_id . '_section_multiple-choice-max-answers" value="' . htmlspecialchars($named_parameters['max answers']) . '">';
				$output .= '<input type="hidden" name="' . $unique_id . '_section_multiple-choice-list-style" value="' . htmlspecialchars($list_style) . '">';

			break;

			case 'inputs' :


			case 'cloze test' :
			case 'cloze' :



			break;


		}




		$output .= '<input type="hidden" name="' . $unique_id . '_section_type" value="' . htmlspecialchars($named_parameters['type']) . '">';
		$output .= '<input type="hidden" name="' . $unique_id . '_section_title" value="' . htmlspecialchars($named_parameters['title']) . '">';
	



		if(!empty($named_parameters['title'])) {
			$output .= '<div class="ci_form_section_title">';
			$output .= $named_parameters['title'];
			$output .= '</div>';

		}



		switch($named_parameters['type']) {

			case 'inputs' :

				$labels = [];

				// *** todo
				// format table with colspan if necessary
				foreach($lines as $value) {	
					$labels[] = trim(preg_replace('/\[.*?\]\s*\*?/','',$value));					
				}

				$one_label_is_defined = false;

			
				foreach($labels as $value) {
					if($value) {
						$one_label_is_defined = true;
						break;
					}
				}



				if($one_label_is_defined) {
					$output .= '<table class="ci_form_section_table">';
				}

				$n = 0;

				foreach($lines as $value) {	

					if($one_label_is_defined) {
						$output .= '<tr class="ci_form_section_table_row">';

					} else {
						$output .= '<div class="ci_form_section_row">';
					}

					$output .= self::ci_form_section_replace_line($named_parameters,$value,$unique_id,$n,$labels[$n]);
					
					if($one_label_is_defined) {
						$output .= '</tr>';

					} else {
						$output .= '</div>';
					}

					$n++;
				}


				if($one_label_is_defined) {
					$output .= '</table>';
				}


			break;



			case 'multiple choice' :
			
				$list_type_ordered = in_array($list_style,$ordered_styles);


				if(!$list_type_ordered) {
					$output .= '<ul class="ci_form_section_list" style="list-style:' . $list_style . '">';

				} else {
					$output .= '<ol class="ci_form_section_list" style="list-style-type:' . $list_style . '">';
				}


				$n = 0;
	
				foreach($lines as $key => $value) {
					$output .= '<li>' . self::ci_form_section_replace_line($named_parameters,$value,$unique_id,$n) . '</li>';
					$n++;
				}

				
				$output .= ($list_type_ordered ? '</ol>' : '</ul>');


			break;



			case 'cloze test' :
			case 'cloze' :

				$suggestions = [];


				if(!empty($named_parameters['suggestions'])) {

					$suggestions = explode(',',$named_parameters['suggestions']);

					foreach($suggestions as $key => $word) {
						$suggestions[$key] = trim(strtolower($word));
					}

				}



				$items = [];
				$answers = [];


				foreach($lines as $key => $value) {

					$example = false;
					$inline_suggestion = null;
					$inline_answer = null;

					$value = trim($value);
					$value = preg_replace('/\s+/',' ', $value);


					// *** to-do
					// in a cloze test the asterisk used
					// to mark an example is redundant

					if($value[0] == '*') {
						$example = true;
						$value = trim(substr($value,1));				
					}


					preg_match('/\[\s*(.+?)\s*\]/',$value,$match);

					if(!empty($match)) {
						$inline_suggestion = strtolower($match[1]);

						if($example) {

							preg_match('/^\s*(.+?)\s*=\s*(.+?)\s*$/',$inline_suggestion,$match);

							if(!empty($match[1])) {
								$inline_suggestion = strtolower($match[1]);
					
								if(!empty($match[2])) {
									$inline_answer = strtolower($match[2]);
								}

							}

							if($example) {
								$answers[] = $inline_suggestion;	//($inline_answer ?? $inline_suggestion);
							}

						}
					
					}


					$items[] = [$value,$example,$inline_suggestion,$inline_answer];
					
				}

		
				$has_inline_suggestions = false;

				// we take the first line which is not an
				// example to determine the kind of test
				foreach($items as $value) {
					list($item, $example, $inline_suggestion, $inline_answer) = $value;

					if(!$example) {
						$has_inline_suggestions = !empty($inline_suggestion);
						break;
					}

				}



				shuffle($suggestions);

				$output .= '<input type="hidden" name="' . $unique_id . '_section_cloze-test-suggestions-type" value="' . htmlspecialchars($has_inline_suggestions ? 'inline' : 'framed') . '" />';
				$output .= '<input type="hidden" name="' . $unique_id . '_section_cloze-test-suggestions" value="' . htmlspecialchars(implode(',',$suggestions)) . '" />';
				$output .= '<input type="hidden" name="' . $unique_id . '_section_cloze-test-answers" value="' . htmlspecialchars(implode(',',$answers)) . '" />';
				

				if(!empty($suggestions)) {

					$output .= '<div class="ci_form_section_cloze_test_suggestions">';
						
				
					foreach($suggestions as $word) {

						$output .= '<span class="ci_cloze_test_suggestions_word' . (in_array($word,$answers) ? '_answered' : '') . '">';
						$output .= $word;
						$output .= '</span>';

						if(in_array($word,$answers)) {
							$key = array_search($word, $answers);
							unset($answers[$key]);
						}

					}

					$output .= '</div>';

				}



				$output .= '<ol class="ci_cloze_test_questions">';

				$n = 0;
				
				foreach($items as $value) {

					list($label, $example, $inline_suggestion, $inline_answer) = $value;

					$replacement = '';

					if($has_inline_suggestions) {
						$replacement .= '<span class="ci_cloze_test_questions_question_suggestion">(' . $inline_suggestion . ')</span> ';
					}



					$replacement .= '<input type="hidden" name="' . $unique_id . '_items_' . $n . '_label" value="' . htmlspecialchars(($example ? '* ' : '') . $label) . '" />';


					if($example) {
						$replacement .= '<span class="ci_cloze_test_questions_question_answered">' . ($inline_answer ? $inline_answer : $inline_suggestion) . '</span>';

					} else {
						$replacement .= '<input name="' . $unique_id . '_items_' . $n . '_value" class="ci_cloze_test_questions_question_blank" data-required="1" type="text" />';
					}

			

					$output .= '<li class="ci_cloze_test_questions_question' . ($example ? '_example' : '') . '">';
					$output .= preg_replace('/\[.*?\]/', $replacement, $label);
					$output .= '</li>';


					$n++;
					
				}

				$output .= '</ol>';			
				


			break;


		}



		$output .= '</div>';


		return $output;


	}




	// check also here
	// https://www.mediawiki.org/wiki/Manual:Parser_functions#The_setFunctionHook_hook

	protected static function parse_function_arguments(&$named_parameters,$argv) {
		
		
		$lines = [];


		foreach($argv as $value) {

			$value = trim($value);
			$value = preg_replace('/\040+/',' ', $value);

			if(empty($value)) {
				continue;
			}


			// square brackets may contain an equal symbol
			// so we temporarily remove it
			$value_ = preg_replace('/\[\s*(.+?)\s*\]\s*\*?/','',$value);

			
			if(strpos($value_,'=') !== false) {
				$values = explode('=',$value_);
				$value_ = trim(str_replace('_',' ',$values[0]));
				if(array_key_exists($value_,$named_parameters)) {
					$named_parameters[$value_] = trim($values[1]);
					continue;
				}

			}

			$lines[] = $value;

		}


		return $lines;

	}



	public static function ci_form_section(Parser $parser, ...$argv) {
	
		$output = self::ci_form_section_process($argv);
		
	 	return array( $output, 'noparse' => true, 'isHTML' => true);

	 }



}


