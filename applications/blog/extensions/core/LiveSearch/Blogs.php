<?php
/**
 * @brief		ACP Live Search Extension
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Blog
 * @since		20 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\extensions\core\LiveSearch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Live Search Extension
 */
class _Blogs
{	
	/**
	 * Check we have access
	 *
	 * @return	void
	 */
	public function hasAccess()
	{
		/* Check Permissions */
		return \IPS\Member::loggedIn()->hasAcpRestriction( 'blog', 'blog', 'blogs_manage' );
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

		/* Then mix in blogs, but make sure we limit to be safe */
		if( $this->hasAccess() )
		{
			/* Perform the search */
			$blogs = \IPS\Db::i()->select(
							"*",
							'blog_blogs',
							array( "word_custom LIKE CONCAT( '%', ?, '%' ) AND lang_id=?", $searchTerm, \IPS\Member::loggedIn()->language()->id ),
							NULL,
							array( 0, 500 )
					)->join(
							'core_sys_lang_words',
							"word_key=CONCAT( 'blogs_blog_', blog_id )"
						);
			
			/* Format results */
			foreach ( $blogs as $blog )
			{
				$blog = \IPS\blog\Blog::constructFromData( $blog );
				
				$results[] = \IPS\Theme::i()->getTemplate( 'livesearch', 'blog', 'admin' )->blog( $blog );
			}
		}
		
		return $results;
	}
}