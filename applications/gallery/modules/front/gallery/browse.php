<?php
/**
 * @brief		Browse the gallery
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		04 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\modules\front\gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Browse the gallery
 */
class _browse extends \IPS\Dispatcher\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\gallery\Category';
	
	/**
	 * Execute
	 * 
	 * @return 	void
	 */
	public function execute()
	{
		if( isset( \IPS\Request::i()->album ) )
		{
			static::$contentModel = 'IPS\gallery\Album';
		}

		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_browse.js', 'gallery' ) );
		parent::execute();
	}

	/**
	 * Determine what to show
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Legacy 3.x redirect */
		if ( isset( \IPS\Request::i()->image ) )
		{
			try
			{
				\IPS\Output::i()->redirect( \IPS\gallery\Image::loadAndCheckPerms( \IPS\Request::i()->image )->url(), '', 301 );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2G189/A', 404, '' );
			}
		}
		
		/* Add RSS feed */
		if ( \IPS\Settings::i()->gallery_rss_enabled )
		{
			\IPS\Output::i()->rssFeeds['gallery_rss_title']	= \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=browse&do=rss', 'front', 'gallery_rss' );
		}

		/* And load data and display */
		if ( isset( \IPS\Request::i()->category ) )
		{
			try
			{
				$category	= \IPS\gallery\Category::loadAndCheckPerms( \IPS\Request::i()->category, 'view' );

				/* Add RSS feed */
				if ( \IPS\Settings::i()->gallery_rss_enabled )
				{
					\IPS\Output::i()->rssFeeds[ \IPS\Member::loggedIn()->language()->addToStack( 'gallery_rss_title_container', FALSE, array( 'sprintf' => array( $category->_title ) ) ) ]	= \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=browse&do=rss&category=' . $category->id, 'front', 'gallery_rss' );
				}

				$this->_category( $category );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2G189/1', 404, '' );
			}
		}
		else if ( isset( \IPS\Request::i()->album ) )
		{
			try
			{
				$album	= \IPS\gallery\Album::loadAndCheckPerms( \IPS\Request::i()->album, 'view' );

				/* Add RSS feed */
				if ( \IPS\Settings::i()->gallery_rss_enabled )
				{
					\IPS\Output::i()->rssFeeds[\IPS\Member::loggedIn()->language()->addToStack('gallery_rss_title_container', FALSE, array('sprintf' => array($album->_title)))] = \IPS\Http\Url::internal('app=gallery&module=gallery&controller=browse&do=rss&album=' . $album->id, 'front', 'gallery_rss');
				}
				$this->_album( $album );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2G189/2', 404, '' );
			}
		}
		else
		{
			$this->_index();
		}
	}

	/**
	 * Show Index
	 *
	 * @return	void
	 */
	protected function _index()
	{
		/* Get stuff */
		$featured	= iterator_to_array( \IPS\gallery\Image::featured( 20, '_rand' ) );
		$new		= iterator_to_array( \IPS\gallery\Image::getItemsWithPermission( array(), NULL, 30 ) );

		/* Online User Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=gallery', 'front', 'gallery' ), array(), 'loc_gallery_browsing' );
		
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('gallery_title');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->index( $featured, $new );
	}

	/**
	 * Show a category listing
	 *
	 * @return	void
	 */
	protected function categories()
	{
		/* Online User Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=browse&do=categories', 'front', 'gallery_categories' ), array(), 'loc_gallery_browsing_categories' );
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('__app_gallery');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->categories();
	}
	
	/**
	 * Show Category
	 *
	 * @param	\IPS\gallery\Category	$category	The category to show
	 * @return	void
	 */
	protected function _category( $category )
	{
		/* Online User Location */
		$permissions = $category->permissions();
		\IPS\Session::i()->setLocation( $category->url(), explode( ",", $permissions['perm_view'] ), 'loc_gallery_viewing_category', array( "gallery_category_{$category->id}" => TRUE ) );
				
		/* Output */
		\IPS\Output::i()->title		= $category->_title;

		/* Need to show albums too */
		$albums	= NULL;

		if( $category->allow_albums )
		{
			$albums	= new \IPS\gallery\Album\Table( NULL, $category );
			$albums->title = 'albums';
			$albums->classes = array( 'ipsDataList_large' );
			$albums	= ( $category->hasAlbums() ) ? (string) $albums : NULL;
		}
		
		if( !count( \IPS\gallery\Image::getItemsWithPermission( array( array( 'image_category_id=? AND image_album_id=?', $category->_id, 0 ) ) ) ) )
		{
			$table = ( $category->childrenCount() or $albums ) ? '' : \IPS\Theme::i()->getTemplate( 'browse' )->noImages( $category );
			
			\IPS\Output::i()->breadcrumb	= array();
			\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=browse', 'front', 'gallery' ), \IPS\Dispatcher::i()->module->_title );
			$parents = iterator_to_array( $category->parents() );
			if ( count( $parents ) )
			{
				foreach( $parents AS $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
			}
			\IPS\Output::i()->breadcrumb[] = array( NULL, $category->_title );
		}
		else
		{
			/* Build table */
			$table = new \IPS\gallery\Image\Table( 'IPS\gallery\Image', $category->url(), array( array( 'image_album_id=?', 0 ) ), $category );
			$table->limit = 50;
			$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'browse' ), 'imageTable' );
			$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'browse' ), $this->getTableRowsTemplate() );
			$table->title = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get('num_images'), array( $category->count_imgs ) );

			if( !$category->allow_comments )
			{
				unset( $table->sortOptions['num_comments'] );
				unset( $table->sortOptions['last_comments'] );
			}

			if( !$category->allow_rating )
			{
				unset( $table->sortOptions['rating'] );
			}
		}
		
		\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'category' ) ) ) ) ] = array( 'type' => 'gallery_image', 'nodes' => $category->_id );
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->category( $category, $albums, (string) $table );
	}

	/**
	 * Show Album
	 *
	 * @param	\IPS\gallery\Album	$album	The album to show
	 * @return	void
	 */
	protected function _album( $album )
	{
		if( !count( \IPS\gallery\Image::getItemsWithPermission( array( array( 'image_album_id=?', $album->id ) ) ) ) )
		{
			/* Show a 'no images' template if there's nothing to display */
			$table = \IPS\Theme::i()->getTemplate( 'browse' )->noImages( $album );
		}
		else
		{
			/* Build table */
			$table = new \IPS\gallery\Image\Table( 'IPS\gallery\Image', $album->url(), array( array( 'image_album_id=?', $album->id ) ), $album->category() );
			$table->limit	= 50;
			$table->sortBy	= \IPS\Request::i()->sortby ? $table->sortBy : $album->_sortBy;
			$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'browse' ), 'imageTable' );
			$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'browse' ), $this->getTableRowsTemplate() );
			$table->title = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get('num_images'), array( $album->count_imgs ) );

			if( !$album->allow_comments )
			{
				unset( $table->sortOptions['num_comments'] );
				unset( $table->sortOptions['last_comments'] );
			}

			if( !$album->allow_rating )
			{
				unset( $table->sortOptions['rating'] );
			}
		}
		
		/* Online User Location */
		$permissions = $album->category()->permissions();
		\IPS\Session::i()->setLocation( $album->url(), explode( ",", $permissions['perm_view'] ), 'loc_gallery_viewing_album', array( $album->_title => FALSE ) );
				
		/* Output */
		\IPS\Output::i()->title			= $album->_title;
		\IPS\Output::i()->breadcrumb	= array();

		\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=browse', 'front', 'gallery' ), \IPS\Dispatcher::i()->module->_title );
		$parents = iterator_to_array( $album->category()->parents() );
		if ( count( $parents ) )
		{
			foreach( $parents AS $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
		}

		\IPS\Output::i()->breadcrumb[] = array( $album->category()->url(), $album->category()->_title );
		\IPS\Output::i()->breadcrumb[] = array( NULL, $album->_title );
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->album( $album, (string) $table );
	}

	/**
	 * Determine which table rows template to use
	 *
	 * @return	string
	 */
	protected function getTableRowsTemplate()
	{
		if( isset( \IPS\Request::i()->cookie['thumbnailSize'] ) AND \IPS\Request::i()->cookie['thumbnailSize'] == 'large' AND \IPS\Request::i()->controller != 'search' )
		{
			return 'tableRowsLarge';
		}
		else if( isset( \IPS\Request::i()->cookie['thumbnailSize'] ) AND \IPS\Request::i()->cookie['thumbnailSize'] == 'rows' AND \IPS\Request::i()->controller != 'search' )
		{
			return 'tableRowsRows';
		}
		else
		{
			return 'tableRowsThumbs';
		}
	}

	/**
	 * Retrieve some images, used for the slider on the image view page
	 *
	 * @return	void
	 */
	protected function getImages()
	{
		$startingPoint	= \IPS\gallery\Image::load( \IPS\Request::i()->image );
		$count = intval( \IPS\Request::i()->count );

		if( \IPS\Request::i()->direction == 'next' )
		{
			$results	= $startingPoint->nextImages( $count );
		}
		else
		{
			$results	= $startingPoint->previousImages( $count );
		}

		$toSend	= array();

		foreach( $results as $result )
		{
			$toSend[]	= \IPS\Theme::i()->getTemplate( 'view' )->imageThumbnail( $result );
		}

		/* Respond or redirect */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( $toSend );
		}
		else
		{
			\IPS\Output::i()->redirect( $startingPoint->url() );
		}
	}
	
	/**
	 * Latest images RSS
	 *
	 * @return	void
	 * @note	We use a template so that we can embed image directly into feed while still allowing it to be customized
	 */
	protected function rss()
	{
		if( !\IPS\Settings::i()->gallery_rss_enabled )
		{
			\IPS\Output::i()->error( 'gallery_rss_offline', '2G189/3', 403, 'gallery_rss_offline_admin' );
		}
		
		$where	= array();

		if( isset( \IPS\Request::i()->category ) )
		{
			try
			{
				$category	= \IPS\gallery\Category::loadAndCheckPerms( \IPS\Request::i()->category, 'read' );
				$where[]	= array( 'image_category_id=?', $category->id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2G189/8', 404, '' );
			}
		}
		else if ( isset( \IPS\Request::i()->album ) )
		{
			try
			{
				$album		= \IPS\gallery\Album::loadAndCheckPerms( \IPS\Request::i()->album, 'read' );
				$where[]	= array( 'image_album_id=?', $album->id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2G189/9', 404, '' );
			}
		}

		$document = \IPS\Xml\Rss::newDocument( isset( $category ) ? $category->url() : ( isset( $album ) ? $album->url() : \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=browse', 'front', 'gallery' ) ), \IPS\Member::loggedIn()->language()->addToStack('gallery_rss_title'), \IPS\Member::loggedIn()->language()->addToStack('gallery_rss_title') );

		foreach ( \IPS\gallery\Image::getItemsWithPermission( $where ) as $image )
		{
			$document->addItem( $image->caption, $image->url(), \IPS\Theme::i()->getTemplate( 'view' )->rssContent( $image ), \IPS\DateTime::ts( $image->updated ), $image->id );
		}
		
		/* @note application/rss+xml is not a registered IANA mime-type so we need to stick with text/xml for RSS */
		\IPS\Output::i()->sendOutput( $document->asXML(), 200, 'text/xml' );
	}

	/**
	 * Edit album
	 *
	 * @return	void
	 */
	protected function editAlbum()
	{
		/* Load album and check permissions */
		try
		{
			$album	= \IPS\gallery\Album::loadAndCheckPerms( \IPS\Request::i()->album, 'read' );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2G189/5', 404, '' );
		}

		if( !$album->canEdit() )
		{
			\IPS\Output::i()->error( 'node_error', '2G189/4', 403, '' );
		}

		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->class .= 'ipsForm_vertical';

		$album->form( $form, TRUE );

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$values['album_owner']	= ( isset( $values['album_owner'] ) ) ? $values['album_owner'] : \IPS\Member::loggedIn();

			if( !$values['album_name'] OR !$values['album_category'] )
			{
				if( !$values['album_name'] )
				{
					$form->elements['']['album_name']->error	= \IPS\Member::loggedIn()->language()->addToStack('form_required');
				}

				if( !$values['album_category'] )
				{
					$form->elements['']['album_category']->error	= \IPS\Member::loggedIn()->language()->addToStack('form_required');
				}

				\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
				return;
			}

			$album->saveForm( $album->formatFormValues( $values ) );
			
			\IPS\Output::i()->redirect( $album->url() );
		}
		
		/* Display form */
		\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
	}

	/**
	 * Delete album
	 *
	 * @return	void
	 */
	protected function deleteAlbum()
	{
		/* Load album and check permissions */
		try
		{
			$album	= \IPS\gallery\Album::loadAndCheckPerms( \IPS\Request::i()->album, 'read' );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2G189/6', 404, '' );
		}

		if( !$album->canDelete() )
		{
			\IPS\Output::i()->error( 'node_error', '2G189/7', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();

		/* Build form to move or delete images */
		$form = new \IPS\Helpers\Form;
		$form->class .= 'ipsForm_vertical';

		$form->add( new \IPS\Helpers\Form\YesNo( "delete_images", TRUE, FALSE, array( 'togglesOff' => array( 'move_image_category', 'move_image_album' ) ) ) );

		$form->add( new \IPS\Helpers\Form\Node( 'move_image_category', NULL, FALSE, array(
			'class'					=> 'IPS\gallery\Category',
			'permissionCheck'		=> 'add',
		), NULL, NULL, NULL, 'move_image_category' ) );

		$form->add( new \IPS\Helpers\Form\Node( 'move_image_album', NULL, FALSE, array(
			'class'					=> 'IPS\gallery\Album',
			'permissionCheck' 		=> function( $node ) use ( $album )
			{
				/* Do we have permission to add? */
				if( !$node->can( 'add' ) )
				{
					return false;
				}

				/* This isn't the album we are deleting right? */
				if ( $node->id == $album->id )
				{
					return false;
				}
				
				return true;
			}
		), NULL, NULL, NULL, 'move_image_album' ) );

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$category = $album->category();
			/* Update the count */
			if( $album->type == $album::AUTH_TYPE_PUBLIC )
			{
				$category->public_albums = $category->public_albums - 1;
			}
			else
			{
				$category->nonpublic_albums = $category->nonpublic_albums - 1;
			}

			$category->save();

			/* Hide the album */
			$album->type = 4;
			$album->save();
			
			/* Are we moving the images? */
			if( !$values['delete_images'] )
			{
				if( ( !isset( $values['move_image_category'] ) OR !( $values['move_image_category'] instanceof \IPS\Node\Model ) ) AND
					( !isset( $values['move_image_album'] ) OR !( $values['move_image_album'] instanceof \IPS\Node\Model ) ) )
				{
					$form->error	= \IPS\Member::loggedIn()->language()->addToStack('gallery_cat_or_album');

					\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
					return;
				}
				
				$moveData = array( 'class' => 'IPS\gallery\Album', 'id' => $album->_id, 'deleteWhenDone' => TRUE );
				if( isset( $values['move_image_category'] ) AND $values['move_image_category'] instanceof \IPS\Node\Model )
				{
					$moveData['moveToClass'] = 'IPS\gallery\Category';
					$moveData['moveTo'] = $values['move_image_category']->_id;
				}
				else
				{
					$moveData['moveToClass'] = 'IPS\gallery\Album';
					$moveData['moveTo'] = $values['move_image_album']->_id;
				}
				
				\IPS\Task::queue( 'core', 'DeleteOrMoveContent', $moveData );
			}
			else
			{
				\IPS\Task::queue( 'core', 'DeleteOrMoveContent', array( 'class' => 'IPS\gallery\Album', 'id' => $album->_id, 'deleteWhenDone' => TRUE ) );
			}

			/* And then redirect */
			if( isset( $moveTo ) )
			{
				\IPS\Output::i()->redirect( $moveTo->url() );
			}
			else
			{
				\IPS\Output::i()->redirect( $album->category()->url() );
			}
		}

		/* Display form */
		\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );
	}
	
	/**
	 * Embed
	 *
	 * @return	void
	 */
	protected function embed()
	{
		\IPS\Request::i()->id = \IPS\Request::i()->album;
		return parent::embed();
	}
}