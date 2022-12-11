<?php
/**
 * @brief		View Blog Entry Controller
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Blog
 * @since		03 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\modules\front\blogs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * View Blog Entry Controller
 */
class _entry extends \IPS\Content\Controller
{	
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\blog\Entry';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		try
		{
			$this->entry = \IPS\blog\Entry::load( \IPS\Request::i()->id );
				
			if ( !$this->entry->canView( \IPS\Member::loggedIn() ) )
			{
				\IPS\Output::i()->error( 'node_error', '2B202/1', 403, '' );
			}

			if ( $this->entry->container()->cover_photo )
			{
				\IPS\Output::i()->metaTags['og:image'] = \IPS\File::get( 'blog_Blogs', $this->entry->container()->cover_photo )->url;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			if ( !isset( \IPS\Request::i()->do ) or \IPS\Request::i()->do !== 'embed' )
			{
				\IPS\Output::i()->error( 'node_error', '2B202/2', 404, '' );
			}
		}

		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		parent::manage();

		$previous = NULL;
		$next = NULL;
		
		/* Prev */
		try
		{
			$previous = \IPS\Db::i()->select( 
					'*', 
					'blog_entries',
					array( 'entry_blog_id=? AND entry_date<? AND entry_status=? AND entry_is_future_entry=0', $this->entry->blog_id, $this->entry->date, "published" ),
					'entry_date DESC'
					,1
					)->first();
			
			$previous = \IPS\blog\Entry::constructFromData( $previous );
		}
		catch ( \UnderflowException $e ) {}
		
		/* Next */
		try
		{
			$next = \IPS\Db::i()->select( 
					'*', 
					'blog_entries',
					array( 'entry_blog_id=? AND entry_date>? AND entry_status=? AND entry_is_future_entry=0', $this->entry->blog_id, $this->entry->date, "published" ),
					'entry_date ASC'
					,1
					)->first();
			
			$next = \IPS\blog\Entry::constructFromData( $next );
		}
		catch ( \UnderflowException $e ) {}
		
		/* Online User Location */
		\IPS\Session::i()->setLocation( $this->entry->url(), $this->entry->onlineListPermissions(), 'loc_blog_viewing_entry', array( $this->entry->name => FALSE ) );		
				
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->entry( $this->entry, $previous, $next );
	}
}