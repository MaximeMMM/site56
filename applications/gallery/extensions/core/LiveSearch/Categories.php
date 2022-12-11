<?php
/**
 * @brief		ACP Live Search Extension
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		04 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\extensions\core\LiveSearch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Live Search Extension
 */
class _Categories
{
	/**
	 * Check we have access
	 *
	 * @return	void
	 */
	public function hasAccess()
	{
		/* Check Permissions */
		return \IPS\Member::loggedIn()->hasAcpRestriction( 'gallery', 'gallery', 'categories_manage' );
	}

	/**
	 * Get the search results
	 *
	 * @param	string	Search Term
	 * @return	array 	Array of results
	 */
	public function getResults( $searchTerm )
	{
		/* Init */
		$results = array();
		$searchTerm = mb_strtolower( $searchTerm );
		
		/* Start with categories */
		if( $this->hasAccess() )
		{
			/* Perform the search */
			$categories = \IPS\Db::i()->select(
							"*",
							'gallery_categories',
							array( "word_custom LIKE CONCAT( '%', ?, '%' ) AND lang_id=?", $searchTerm, \IPS\Member::loggedIn()->language()->id ),
							NULL,
							NULL
					)->join(
							'core_sys_lang_words',
							"word_key=CONCAT( 'gallery_category_', category_id )"
						);
			
			/* Format results */
			foreach ( $categories as $category )
			{
				$category = \IPS\gallery\Category::constructFromData( $category );
				
				$results[] = \IPS\Theme::i()->getTemplate( 'livesearch', 'gallery', 'admin' )->category( $category );
			}
		}

		return $results;
	}
}