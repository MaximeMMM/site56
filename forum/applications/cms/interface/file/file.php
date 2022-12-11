<?php
/**
 * @brief		Pages Download Handler
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		27 May 2015
 * @version		SVN_VERSION_NUMBER
 */

require_once str_replace( 'applications/cms/interface/file/file.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
\IPS\Dispatcher\Front::i();

try
{
	/* Load member */
	$member = \IPS\Member::loggedIn();
	
	/* Set up autoloader for CMS */

	/* Init */
	$databaseId  = intval( \IPS\Request::i()->database );
	$database    = \IPS\cms\Databases::load( $databaseId );
	$recordId    = intval( \IPS\Request::i()->record );
	$fileName    = urldecode( \IPS\Request::i()->file );
	$recordClass = '\IPS\cms\Records' . $databaseId;
	
	try
	{
		$record = $recordClass::load( $recordId );
	}
	catch( \OutOfRangeException $ex )
	{
		\IPS\Output::i()->error( 'no_module_permission', '2T279/1', 403, '' );
	}
	
	if ( ! $record->canView() )
	{
		\IPS\Output::i()->error( 'no_module_permission', '2T279/2', 403, '' );
	}

	/* Get file and data */
	try
	{
		$file = \IPS\File::get( 'core_Records', $fileName );
	}
	catch( \Exception $ex )
	{
		\IPS\Output::i()->error( 'no_module_permission', '2T279/3', 404, '' );
	}
	
	$headers = array_merge( \IPS\Output::getCacheHeaders( time(), 360 ), array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'attachment', $file->originalFilename ), "X-Content-Type-Options" => "nosniff" ) );
	
	/* Send headers and print file */
	\IPS\Output::i()->sendStatusCodeHeader( 200 );
	\IPS\Output::i()->sendHeader( "Content-type: " . \IPS\File::getMimeType( $file->originalFilename ) . ";charset=UTF-8" );

	foreach( $headers as $key => $header )
	{
		\IPS\Output::i()->sendHeader( $key . ': ' . $header );
	}
	\IPS\Output::i()->sendHeader( "Content-Length: " . $file->filesize() );

	$file->printFile();
	exit;
}
catch ( \UnderflowException $e )
{
	\IPS\Dispatcher\Front::i();
	\IPS\Output::i()->sendOutput( '', 404 );
}