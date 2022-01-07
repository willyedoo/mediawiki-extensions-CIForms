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
 * @author thomas-topway-it <thomas.topway.it@mail.com>
 * @copyright Copyright © 2021, https://culturaitaliana.org
 */

$( function () {

	function escape( s ) {
		return ( String( s ) )
			.replace( /&/g, '\x26' )
			.replace( /'/g, '\x27' )
			.replace( /"/g, '\x22' )
			.replace( /</g, '\x3C' )
			.replace( />/g, '\x3E' );
	}

	var site_key = mw.config.get( 'ci_forms_google_recaptcha_site_key' );

	mw.loader.getScript( 'https://www.google.com/recaptcha/api.js?render=' + site_key ).then( function () {

		if ( $( 'input[name="g-recaptcha-response"]' ).length ) {
			grecaptcha.ready( function () {
				grecaptcha.execute( site_key, { action: 'validate_captcha' } ).then( function ( token ) {
					$( 'input[name="g-recaptcha-response"]' ).val( token );

				} ).catch( function ( error ) {} );
			} );
		}
	},
	function ( e ) {
		mw.log.error( e.message );
	} );

	$( '.ci_form li' ).each( function ( index ) {

		var el = this;
		var section_el = $( this ).closest( '.ci_form_section' );
		var radioForCheckboxes = $( section_el ).find( '.radio_for_required_checkboxes' ).first();
		var max_answers = $( section_el ).find( 'input[type=hidden][name$=_multiple-choice-max-answers]' ).val();

		$( this ).find( 'input[type=text]' ).on( 'click', function () {
			var count = $( section_el ).find( 'input[type=checkbox]:checked' ).length;

			if ( count >= max_answers ) {
				alert( 'maximum ' + max_answers + ' choices' );
				return false;
			}

			if ( $( this ).attr( 'data-required' ) === 1 ) {
				$( this ).prop( 'required', true );
			}

			$( el ).find( 'input[type=radio]' ).prop( 'checked', true );
			$( el ).find( 'input[type=checkbox]' ).prop( 'checked', true );

			radioForCheckboxes[ 0 ].checked = ( !!count );

		} );

		$( this ).find( 'input[type=checkbox]' ).on( 'click', function () {
			var count = $( section_el ).find( 'input[type=checkbox]:checked' ).length;

			if ( count > max_answers ) {
				alert( 'maximum ' + max_answers + ' choices' );
				return false;
			}

			radioForCheckboxes[ 0 ].checked = ( !!count );

		} );

	} );

	// we cannot use form on submit because
	// is triggered after the native validation

	$( this ).find( 'input[type=radio]' ).on( 'click', function () {
		var section_el = $( this ).closest( '.ci_form_section' );

		$( section_el ).find( 'li' ).each( function () {
			var el = this;

			$( this ).find( 'input[type=radio][name$=_selected]:checked' ).each( function () {
				$( el ).find( 'input[type=text][data-required="1"]' ).prop( 'required', true );
			} );

			$( this ).find( 'input[type=radio][name$=_selected]:not(:checked)' ).each( function () {
				$( el ).find( 'input[type=text]' ).prop( 'required', false );
			} );

		} );

	} );

	$( this ).find( 'input[type=checkbox]' ).on( 'click', function () {
		var section_el = $( this ).closest( '.ci_form_section' );

		$( section_el ).find( 'li' ).each( function () {
			var el = this;

			$( this ).find( 'input[type=checkbox][name$=_selected]:checked' ).each( function () {
				$( el ).find( 'input[type=text][data-required="1"]' ).prop( 'required', true );
			} );

			$( this ).find( 'input[type=checkbox][name$=_selected]:not(:checked)' ).each( function () {
				$( el ).find( 'input[type=text]' ).prop( 'required', false );
			} );

		} );

	} );

	// cloze test,
	// fill-in at least half + 1 questions

	$( '.ci_form' ).submit( function ( evt ) {
		var form_element = $( this );

		$( this ).find( '.ci_form_section_cloze_test_list' ).each( function () {
			var section = $( this ).closest( '.ci_form_section' );
			var question_name = $( section ).find( '.ci_form_section_title' ).text();

			if ( !question_name ) {
				question_name = $( form_element ).find( '.ci_form_title' ).text();
			}

			var inputs = 0;
			var submitted = 0;

			$( this ).find( 'input[type=text][name$=_value]' ).each( function () {
				var val = $( this ).val().trim();

				if ( val !== '' && val !== null ) {
					submitted++;
				}

				inputs++;
			} );

			var min = Math.floor( inputs / 2 ) + 1;

			if ( submitted < min ) {
				evt.preventDefault();
				alert( 'Please enter at least ' + min + ' answers for the question "' + escape( question_name ) + '"' );
				return false;
			}

		} );

	} );

} );
