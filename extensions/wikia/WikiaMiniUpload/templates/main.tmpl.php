<div id="ImageUploadError"></div>

<table cellspacing="0" style="width: 100%;" id="ImageUploadInputTable">

	<tr id="ImageUploadUpload">
		<td><h1><?= wfMessage( 'wmu-upload' )->escaped() ?></h1></td>
		<td>
<?php
global $wgScript, $wgStylePath, $wgExtensionsPath, $wgUser, $wgEnableUploads;

if ( !$wgEnableUploads ) {
	echo wfMessage( "wmu-uploaddisabled" )->escaped();
} else if ( !$wgUser->isAllowed( 'upload' ) ) {
	if ( !$wgUser->isLoggedIn() ) {
		echo '<a id="ImageUploadLoginMsg">' . wfMessage( 'wmu-notlogged' )->escaped() . '</a>';
	} else {
		echo wfMessage( 'wmu-notallowed' )->escaped();
	}
} else if ( wfReadOnly() ) {
	echo wfMessage( 'wmu-readonly' )->escaped();
} else {
	if ( $error ) {
		?>
			<span id="WMU_error_box"><?= $error ?></span>
			<?php
	}
	?>
			<form onsubmit="return $.AIM.submit(this, WMU_uploadCallback)" action="<?= $wgScript ?>?action=ajax&rs=WMU&method=uploadImage" id="ImageUploadForm" method="POST" enctype="multipart/form-data">
				<input id="ImageUploadFile" name="wpUploadFile" type="file" size="32" />
				<input type="submit" value="<?= wfMessage( 'wmu-upload-btn' )->escaped() ?>" onclick="return WMU_upload(event);" />
			</form>
	<?php
}
?>
		</td>
	</tr>
	<tr id="ImageUploadFind">
		<td><h1><?= wfMessage( 'wmu-find' )->escaped() ?></h1></td>
		<td>
			<input onkeydown="WMU_trySendQuery(event);" type="text" id="ImageQuery" />
			<input onclick="WMU_trySendQuery(event);" type="button" value="<?= wfMessage( 'wmu-find-btn' )->escaped() ?>" />
			<img src="<?= $wgStylePath ?>/common/images/ajax.gif" id="ImageUploadProgress2" style="visibility: hidden;"/>
		</td>
	</tr>
</table>

<div id="WMU_results">
	<?= $result ?>
</div>