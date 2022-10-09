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
 * @copyright Copyright © 2021-2022, https://wikisphere.org
 */

$( document ).ready( function () {
	// display every 3 days
	if ( !mw.cookie.get( 'ciforms-check-latest-version' ) ) {
		mw.loader.using( 'mediawiki.api', function () {
			new mw.Api()
				.postWithToken( 'csrf', {
					action: 'ciforms-check-latest-version'
				} )
				.done( function ( res ) {
					if ( 'ciforms-check-latest-version' in res ) {
						if ( res[ 'ciforms-check-latest-version' ].result === 2 ) {
							var messageWidget = new OO.ui.MessageWidget( {
								type: 'warning',
								label: new OO.ui.HtmlSnippet(
									mw.msg( 'ciforms-jsmodule-pageproperties-outdated-version' )
								),
								// *** this does not work before ooui v0.43.0
								showClose: true
							} );
							var closeFunction = function () {
								var three_days = 3 * 86400;
								mw.cookie.set( 'ciforms-check-latest-version', true, {
									path: '/',
									expires: three_days
								} );
								$( messageWidget.$element ).parent().remove();
							};
							messageWidget.on( 'close', closeFunction );
							$( '.ciforms-manage-pager-table' )
								.eq( 0 )
								.before( $( '<div><br/></div>' ).prepend( messageWidget.$element ) );

							if (
								!messageWidget.$element.hasClass(
									'oo-ui-messageWidget-showClose'
								)
							) {
								messageWidget.$element.addClass(
									'oo-ui-messageWidget-showClose'
								);
								var closeButton = new OO.ui.ButtonWidget( {
									classes: [ 'oo-ui-messageWidget-close' ],
									framed: false,
									icon: 'close',
									label: OO.ui.msg( 'ooui-popup-widget-close-button-aria-label' ),
									invisibleLabel: true
								} );
								closeButton.on( 'click', closeFunction );
								messageWidget.$element.append( closeButton.$element );
							}
						}
					}
				} );
		} );
	}

	$( '.ciforms-manage-button-export' ).each( function () {
		var $buttonExport = $( this );

		var href = $buttonExport.data().ooui.href;

		var buttonMenu = new OO.ui.ButtonMenuSelectWidget( {
			label: mw.msg( 'ci-forms-manage-pager-button-export' ),
			icon: 'menu',
			flags: [ 'progressive', 'primary' ],
			menu: {
				items: [
					new OO.ui.MenuOptionWidget( {
						data: 'csv',
						label: mw.msg( 'ci-forms-manage-pager-button-export-csv' )
					} ),
					new OO.ui.MenuOptionWidget( {
						data: 'excel',
						label: mw.msg( 'ci-forms-manage-pager-button-export-excel' )
					} )
				]
			}
		} );

		var panelLayout = new OO.ui.PanelLayout( {
			padded: false,
			expanded: false,
			classes: [ 'ci-forms-manage-pager-panel-layout' ]
		} );

		buttonMenu.getMenu().on( 'choose', function ( menuOption ) {
			var data = menuOption.getData();
			window.location.assign( href.replace( 'format=csv', 'format=' + data ) );
		} );

		$buttonExport.replaceWith( panelLayout.$element.append( buttonMenu.$element ) );
	} );
} );
