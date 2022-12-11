<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		24 Oct 2014
 * @version		SVN_VERSION_NUMBER
 */


$options	= array(
);

if( \IPS\Db::i()->checkForTable( 'gallery_albums_main' ) )
{
	$_options	= iterator_to_array( \IPS\Db::i()->select( 'album_id, album_name', 'gallery_albums_main', 'album_is_global=1 AND album_node_level=0' )->setKeyField('album_id')->setValueField('album_name') );

	$options[]	= new \IPS\Helpers\Form\Select( '42000_members_album', 0, TRUE, array( 'options' => $_options ) );
	$options[]	= new \IPS\Helpers\Form\Checkbox( '42000_new_members_album', 1, FALSE );
}
