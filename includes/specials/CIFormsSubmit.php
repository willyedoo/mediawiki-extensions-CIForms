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


class CIFormsSubmit extends SpecialPage {

	public function __construct() {
		// not list in the special pages index
		$listed = false;
		parent::__construct( 'ci-forms-submit', '', $listed );
	}




	public function execute( $par ) {


		$request = $this->getRequest();


		$this->setHeaders();
		$this->outputHeader();


		$out = $this->getOutput();


		//$out->addModuleStyles( 'ext.CIForms.validation' );


		global $wgResourceBasePath;
		$css = '<link rel="stylesheet" href="' . $wgResourceBasePath . '/extensions/CIForms/resources/style.css" />';

		$out->addHeadItem('ci_forms_css', $css );


		$post = $_POST;	//$request->getArray( 'data' );
//print_r($post);


		global $wgEnableEmail;
		global $wgCIFormsSenderEmail;


		if(!$wgEnableEmail) {
			return $this->exit($out,$this->msg( 'ci-forms-email-not-enabled'));
		}




		list($result,$message,$captcha_message) = $this->check_captcha($post) + [null,null,null];



		if($result === false) {
			return $this->exit($out,$this->msg( $message, $captcha_message,$wgCIFormsSenderEmail));
		}


		
		$form_result = $this->parseForm($post);


		global $wgCIFormsSubmitEmail;

		//print_r($form_result);

		$submit = null;

		if(!empty($form_result['form_values']['submit'])) {
			$submit = $form_result['form_values']['submit'];

		} else if(!empty($wgCIFormsSubmitEmail)) {
			$submit = $wgCIFormsSubmitEmail;
		}


		if(empty($submit)) {
			return $this->exit($out,$this->msg( 'ci-forms-recipient-not-found'));
		}

		if(!Sanitizer::validateEmail($submit)) {
			return $this->exit($out,$this->msg( 'ci-forms-recipient-invalid'));
		}

		
		$table = $this->create_table($form_result['form_values'],$form_result['sections']);

		//echo $table;


		$subject = $this->msg( 'ci-forms-email-subject',$form_result['form_values']['title'],$form_result['form_values']['pagename']);

		$from = new MailAddress( $wgCIFormsSenderEmail );

		$to = new MailAddress( $submit );


		$status = UserMailer::send( $to, $from, $subject, $table);



		$message = null;


		if(!is_object( $status ) || !$status->ok ) {

			global $wgCIFormsErrorMessage;


			if(!empty($form_result['form_values']['error-message'])) {
				$error_message = $form_result['form_values']['error-message'];
				
			} else if(!empty($wgCIFormsErrorMessage)) {
				$message = $wgCIFormsErrorMessage;
			
			} else {
				$message = $this->msg( 'ci-forms-dispatch-error', $wgCIFormsSenderEmail);
			}


		} else {

			global $wgCIFormsSuccessMessage;

			if(!empty($form_result['form_values']['success-message'])) {
				$message = $form_result['form_values']['success-message'];
				
			} else if(!empty($wgCIFormsSuccessMessage)) {
				$message = $wgCIFormsSuccessMessage;
			
			} else {
				$message = $this->msg( 'ci-forms-dispatch-success');
			}

		}


		return $this->exit($out,$message);


	}



	protected function exit($out,$message) {
		$html = '<p>' . $message . '</p>';
		$out->addHTML($html);
	}



	protected function create_table($form_values,$sections) {

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



		$output = '';


		$output .= '<div class="ci_form_table">';


		
		foreach($sections as $key => $section) {
	 

			$output .= '<div class="ci_form_section">';


			if(!empty($section['title'])) {
				$output .= '<div class="ci_form_section_title">';
				$output .= section['title'];
				$output .= '</div>';
			}


			switch($section['type']) {

				case 'inputs' :

					$output .= '<table class="ci_form_section_table"">';
 
	
					foreach($section['items'] as $value) {

						$label = trim(preg_replace('/\[\s*(.*?)\s*\]\s*\*?/','',$value['label']));

						$output .= '<tr class="ci_form_section_table_row">';
						$output .= '<th class="ci_form_section_table_cell_left">' . $label . '</th>';
						$output .= '<td class="ci_form_section_table_cell_right">';
						$output .= '<input disabled="disabled" type="text" value="' . htmlspecialchars($value['value']) . '" />';
						$output .= '</td></tr>';
	
					}
		
					$output .= '</table>';


				break;


				case 'multiple choice' :

					$list_type_ordered = in_array($section['multiple-choice-list-style'],$ordered_styles);


					if(!$list_type_ordered) {
						$output .= '<ul class="ci_form_section_list" style="list-style:' . $section['multiple-choice-list-style'] . '">';

					} else {
						$output .= '<ol class="ci_form_section_list" style="list-style-type:' . $section['multiple-choice-list-style'] . '">';
					}


	
					foreach($section['items'] as $value) {
					
						$label = $value['label'];
				
						$ii = -1;

						$output .= '<li>';

						$output .= '<input disabled="disabled" ' . ($value['value'] ? 'checked="checked" ' : '') . 'type="' . ($section['multiple-choice-max-answers'] == 1 ? 'radio' : 'checkbox') . '" />';
		

						$label = preg_replace_callback('/\[\s*\]\s*\*?/', function($matches) use ($value,&$ii) {
							$ii++;
							return '<input disabled="disabled" type="text" value="' . htmlspecialchars($value['inputs'][$ii]) . '" />';
						},$label);


						$output .= $label;

						$output .= '</li>';				
	 
					}


					$output .= ($list_type_ordered ? '</ol>' : '</ul>');

				break;



				case 'cloze test' :

					$output .= '<ol class="ci_cloze_test_questions">';


					foreach($section['items'] as $value) {

						$label = trim($value['label']);

						$example = ($label[0] == '*');

						if($example) {
							$label = trim(substr($label,1));
						}

						

						$output .= '<li class="ci_cloze_test_questions_question' . ($example ? '_example' : '') . '">';
						

						$output .= preg_replace_callback('/\[\s*(.*?)\s*\]\s*\*?/', function($matches) use ($value,$section,$example,&$ii) {
					
							$inline_suggestion = null;
							$inline_answer = null;

							if(!empty($matches[1])) {

								$inline_suggestion = strtolower($matches[1]);

								if($example) {

									$match_ = preg_match('/^\s*(.+?)\s*=\s*(.+?)\s*$/',$inline_suggestion,$match);

									if(!empty($match_[1])) {
										$inline_suggestion = strtolower($match_[1]);
					
										if(!empty($match_[2])) {
											$inline_answer = strtolower($match_[2]);
										}

									}

								}

							}



							$replacement_inner = '';

							if($section['cloze-test-suggestions-type'] == 'inline') {
								$replacement_inner .= '<span class="ci_cloze_test_questions_question_suggestion">(' . $inline_suggestion . ')</span> ';
							}


							if($example) {
								$replacement_inner .= '<span class="ci_cloze_test_questions_question_answered">' . ($inline_answer ? $inline_answer : $inline_suggestion) . '</span> ';

							} else {
		 						$replacement_inner .= '<input type="text" disabled="disabled" value="' . htmlspecialchars($value['value']) . '" />';
							}


							return $replacement_inner;
	
						},$label);


						$output .= '</li>';
						

					}


					$output .= '</ol>';
					
				break;

			}


			$output .= '</div>';

		}


		$output .= '</div>';


		return $output;

	}




	protected function check_captcha($post) {
		
		global $wgCIFormsGoogleRecaptchaSecret;


		if (empty($wgCIFormsGoogleRecaptchaSecret)) {
			return [false,'ci-forms-google-recaptcha-secret-not-set'];
		}


		if (empty($post['g-recaptcha-response'])) {
			return [false,'ci-forms-recaptcha-challenge-not-found'];
		}


		$captcha = $post['g-recaptcha-response'];

		$response = file_get_contents(
			"https://www.google.com/recaptcha/api/siteverify?secret=" . $wgCIFormsGoogleRecaptchaSecret . "&response=" . $captcha . "&remoteip=" . $_SERVER['REMOTE_ADDR']
		);



		// use json_decode to extract json response
		$response = json_decode($response,true);

		if ($response['success'] === false) {
			return [false,'ci-forms-recaptcha-error',@$response['error-codes'][0]];
		}



		//... The Captcha is valid you can continue with the rest of your code
		//... Add code to filter access using $response . score

		if ($response['success'] == true && $response['score'] <= 0.5) {
			return [false,'ci-forms-recaptcha-negative-score'];
		}


		return [true];

	}




	protected function parseForm($post) {

		$props = [];
		$labels = [];
		$values = [];
		$inputs = [];


		$form_values = [];

		$exclude = ['form', 'g-recaptcha-response', 'radio-for-required-checkboxes'];

		foreach($post as $i => $value) {

			$value = trim($value);

			list($section, $a, $b, $c, $d) = explode('_',$i) + [null,null,null,null,null];

			if(in_array($section,$exclude)) {
				if($section == 'form') {
					$form_values[$a] = $value;
				}
				continue;
			}

			if(!array_key_exists($section,$props)) {
				$props[$section] = [];
				$labels[$section] = [];
				$values[$section] = [];
				$inputs[$section] = [];
			}
		

			switch($a) {

				case 'section': 
					$props[$section][$b] = $value;
				break;

				case 'items':

					if($c === 'label') {
						$labels[$section][$b] = $value;
					}

					if($c === 'value') {
						$values[$section][$b] = $value;
					}


					if($props[$section]['type'] == 'multiple choice') {

						if($b === 'value' && $value !== null && $value !== '') {
							//$values[$section] = $value;
							$values[$section][$value * 1] = true;
						}

						if($c === 'input') {

							if(!array_key_exists($b,$inputs[$section])) {
								$inputs[$section][$b] = [];
							}

							$inputs[$section][$b][$d] = $value;
						}

					}

				break;

			}

		} 


		$sections = [];

		foreach($props as $section => $value_) {

			$obj = array_merge($value_,['items' => []]);
	
			foreach($labels[$section] as $i => $row) {
				$obj['items'][$i] = [
					'label' => $row,
					'value' => (!empty($values[$section][$i]) ? $values[$section][$i]: null)
				];

				if($value_['type'] == 'multiple choice') {
					$obj['items'][$i]['inputs'] = (!empty($inputs[$section][$i]) ? $inputs[$section][$i]: null);
				}
			}


			$sections[] = $obj;

		}


		return ['form_values' => $form_values, 'sections' => $sections];

	}

}


