<?php
/**
 * @brief		Submit
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Blog
 * @since		10 Mar 2014
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
 * Submit
 */
class _submit extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\blog\Entry::canCreate( \IPS\Member::loggedIn(), NULL, TRUE );
		
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack( 'submit_entry' );
		
		/* Load Blog */
		try
		{
			/* Can we add to this Blog? */
			$blog = \IPS\blog\Blog::load( \IPS\Request::i()->id );
			\IPS\blog\Entry::canCreate( \IPS\Member::loggedIn(), $blog, TRUE );
			
			$form = \IPS\blog\Entry::create( $blog );
			$formTemplate = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'submit', 'blog' ) ), 'submitFormTemplate' ) );
			
			\IPS\Session::i()->setLocation( $blog->url(), array(), 'loc_blog_adding_entry', $blog->member_id ? array( $blog->_title => FALSE ) : array( "blogs_blog_{$blog->id}" => TRUE ) );
            \IPS\Output::i()->breadcrumb[] = array( $blog->url(), $blog->_title );
			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'submit' )->submit( $formTemplate );
		}
		catch ( \OutOfRangeException $e )
		{
			$form = new \IPS\Helpers\Form( 'select_blog', 'continue' );
			$form->class = 'ipsForm_vertical ipsForm_noLabels';
			$form->add( new \IPS\Helpers\Form\Node( 'blog_select', NULL, TRUE, array(
					'url'					=> \IPS\Http\Url::internal( 'app=blog&module=blogs&controller=submit', 'front', 'blog_submit' ),
					'class'					=> 'IPS\blog\Blog',
					'permissionCheck'		=> 'add',
					'forceOwner'		=> \IPS\Member::loggedIn(),
			) ) );
			
			if ( $values = $form->values() )
			{
				$url = \IPS\Http\Url::internal( 'app=blog&module=blogs&controller=submit', 'front', 'blog_submit' )->setQueryString( 'id', $values['blog_select']->_id );
				\IPS\Output::i()->redirect( $url );
			}
			
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->blogSelector( $form );
		}
	}
}