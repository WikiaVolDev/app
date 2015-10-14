<?php
/*
 * @author Inez Korczyński
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	exit( 1 );
}

$wgExtensionCredits['other'][] = [
        'name' => 'WikiaMiniUpload (Add Images)',
        'author' => [
			'Inez Korczyński',
			'Bartek Łapiński'
		],
		'descriptionmsg' => 'wmu-desc',
		'url' => 'https://github.com/Wikia/app/tree/dev/extensions/wikia/WikiaMiniUpload'
];

$wgExtensionMessagesFiles['WikiaMiniUpload'] = __DIR__ . '/WikiaMiniUpload.i18n.php';
$wgHooks['EditPage::showEditForm:initial2'][] = 'WMUSetup';

function WMUSetup( $editform ) {
	global $wgHooks;

	if ( get_class( RequestContext::getMain()->getSkin() ) === 'SkinOasis' ) {
		$wgHooks['MakeGlobalVariablesScript'][] = 'WMUSetupVars';
		if ( isset ( $editform->ImageSeparator ) ) {
		} else {
			$editform->ImageSeparator = ' - ' ;
		}
	}
	return true;
}

function WMUSetupVars( Array &$vars ) {
	global $wgFileBlacklist, $wgCheckFileExtensions, $wgStrictFileExtensions, $wgFileExtensions;

	$vars['wgEnableWikiaMiniUploadExt'] = true;

	$vars['wmu_back'] = wfMessage( 'wmu-back' )->escaped();
	$vars['wmu_imagebutton'] = wfMessage( 'wmu-imagebutton' )->escaped() ;
	$vars['wmu_close'] = wfMessage( 'wmu-close' )->escaped();
	$vars['wmu_no_preview'] = wfMessage( 'wmu-no-preview' )->escaped();
	$vars['wmu_warn1'] = wfMessage( 'wmu-warn1' )->escaped();
	$vars['wmu_warn2'] = wfMessage( 'wmu-warn2' )->escaped();
	$vars['wmu_warn3'] = wfMessage( 'wmu-warn3' )->escaped();
	$vars['wmu_bad_extension'] = wfMessage( 'wmu-bad-extension' )->escaped();
	$vars['filetype_missing'] = wfMessage( 'filetype-missing' )->escaped();
	$vars['file_extensions'] = $wgFileExtensions;
	$vars['file_blacklist'] = $wgFileBlacklist;
	$vars['check_file_extensions'] = $wgCheckFileExtensions;
	$vars['strict_file_extensions'] = $wgStrictFileExtensions;
	$vars['wmu_show_license_message'] = wfMessage( 'wmu-show-license-msg' )->escaped();
	$vars['wmu_hide_license_message'] = wfMessage( 'wmu-hide-license-msg' )->escaped();
	$vars['wmu_max_thumb'] = wfMessage( 'wmu-max-thumb' )->escaped();
	$vars['badfilename'] = wfMessage( 'badfilename' )->escaped();

	return true;
}

$wgAjaxExportList[] = 'WMU';

function WMU() {
	global $wgRequest, $wgGroupPermissions, $wgAllowCopyUploads;

	// Overwrite configuration settings needed by image import functionality
	$wgAllowCopyUploads = true;
	$wgGroupPermissions['user']['upload_by_url'] = true;

	$method = $wgRequest->getVal( 'method' );
	$wmu = new WikiaMiniUpload();

	if ( method_exists( $wmu, $method ) ) {
		$html = $wmu->$method();
		$ar = new AjaxResponse( $html );
		$ar->setContentType( 'text/html; charset=utf-8' );
	} else {
		$errorMessage = 'WMU::' . $method . ' does not exist';

		\Wikia\Logger\WikiaLogger::instance()->error( $errorMessage );

		$payload = json_encode( [ 'message' => $errorMessage ] );
		$ar = new AjaxResponse( $payload );
		$ar->setResponseCode( '501 Not implemented' );
		$ar->setContentType( 'application/json; charset=utf-8' );
	}
	return $ar;
}

$wgAutoloadClasses['WikiaMiniUpload'] = __DIR__ . '/WikiaMiniUpload_body.php';

$wgResourceModules['ext.wikia.WMU'] = [
	'scripts' => 'js/WMU.js',
	'styles' => 'css/WMU.css',
	'dependencies' => [ 'wikia.yui', 'jquery.aim' ],
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'wikia/WikiaMiniUpload'
];
