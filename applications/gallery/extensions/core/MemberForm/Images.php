<?php
/**
 * @brief		Admin CP Member Form
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		07 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\extensions\core\MemberForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin CP Member Form
 */
class _Images
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member				$member	Existing Member
	 * @return	void
	 */
	public function process( &$form, $member )
	{
		$form->addMessage( "<a href='" . \IPS\Http\Url::internal( 'app=gallery&module=stats&controller=member&do=images&id=' . $member->member_id ) . "'>" . \IPS\Member::loggedIn()->language()->addToStack('gallery_stats') . '</a>', '', FALSE );
		$form->add( new \IPS\Helpers\Form\YesNo( 'remove_gallery_access', !$member->members_bitoptions['remove_gallery_access'], NULL, array( 'togglesOn' => array( 'remove_gallery_upload' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'remove_gallery_upload', !$member->members_bitoptions['remove_gallery_upload'], NULL, array(), NULL, NULL, NULL, 'remove_gallery_upload' ) );
	}
	
	/**
	 * Save
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\Member			$member	The member
	 * @return	void
	 */
	public function save( $values, &$member )
	{
		$member->members_bitoptions['remove_gallery_access']	= !$values['remove_gallery_access'];
		$member->members_bitoptions['remove_gallery_upload']	= !$values['remove_gallery_upload'];
	}
}