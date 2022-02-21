<?php
/**
 * This file is part of the MediaWiki extension Wikisphere.
 *
 * Wiskiphere_Subpages is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * Wiskiphere_Subpages is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Wiskiphere_Subpages.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <thomas.topway.it@mail.com>
 * @copyright Copyright ©2021-2022, https://wikisphere.org
 */

class PrevNextNavigationRendererCIForms extends MediaWiki\Navigation\PrevNextNavigationRenderer {

	/** @var array */
	private $additional_query_parameters;

	/**
	 * @var MessageLocalizer
	 */
	private $messageLocalizer;

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param array $additional_query_parameters
	 */
	public function __construct( MessageLocalizer $messageLocalizer, $additional_query_parameters ) {
		// ***edited
		parent::__construct( $messageLocalizer );
		$this->messageLocalizer = $messageLocalizer;
		$this->additional_query_parameters = $additional_query_parameters;
	}

	/**
	 * Helper function for buildPrevNextNavigation() that generates links
	 *
	 * @param Title $title Title object to link
	 * @param int $offset
	 * @param int $limit
	 * @param array $query Extra query parameters
	 * @param string $link Text to use for the link; will be escaped
	 * @param string $tooltipMsg Name of the message to use as tooltip
	 * @param string $class Value of the "class" attribute of the link
	 * @return string HTML fragment
	 */
	private function numLink(
		Title $title,
		$offset,
		$limit,
		array $query,
		$link,
		$tooltipMsg,
		$class
	) {
		$query = [ 'limit' => $limit, 'offset' => $offset ] + $query;
		$tooltip = $this->messageLocalizer->msg( $tooltipMsg )
			->title( $title )
			->numParams( $limit )
			->text();

		// ***edited
		$query = array_merge( $this->additional_query_parameters, $query );

		return Html::element(
			'a',
			[
				'href' => $title->getLocalURL( $query ),
				'title' => $tooltip,
				'class' => $class,
			],
			$link
		);
	}

}
