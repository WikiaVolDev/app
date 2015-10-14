<input id="ImageUploadExtraId" type="hidden" value="<?= urlencode( $extraId ) ?>" />
<?php
$file_mwname = new FakeLocalFile( Title::newFromText( $mwname, 6 ), RepoGroup::singleton()->getLocalRepo() );
$file_name = new LocalFile( Title::newFromText( $partname . '.' . $extension, 6 ), RepoGroup::singleton()->getLocalRepo() );
echo wfMessage( 'wmu-conflict-inf', $file_name->getName() )->parse();
?>
<table cellspacing="0" id="ImageUploadConflictTable">
	<tr>
		<td style="border-right: 1px solid #CCC;">
			<h2><?= wfMessage( 'wmu-rename' )->escaped() ?></h2>
			<div style="margin: 5px 0;">
				<input type="text" id="ImageUploadRenameName" value="<?= $partname ?>" />
				<label for="ImageUploadRenameName">.<?= $extension ?></label>
	                        <input id="ImageUploadRenameExtension" type="hidden" value="<?= $extension ?>" />
				<input type="button" value="<?= wfMessage( 'wmu-insert' )->escaped()  ?>" onclick="WMU_insertImage('rename');" />
			</div>
		</td>
		<td>
			<h2><?= wfMessage( 'wmu-existing' )->escaped()  ?></h2>
			<div style="margin: 5px 0;">
				<input type="button" value="<?= wfMessage( 'wmu-insert' )->escaped()  ?>" onclick="WMU_insertImage('existing');" />
			</div>
		</td>
	</tr>
	<tr id="ImageUploadCompare">
		<td style="border-right: 1px solid #CCC;">
			<?= $file_mwname->transform( [ 'width' => 265, 'height' => 205 ] )->toHtml() ?>
		</td>
		<td>
			<input type="hidden" id="ImageUploadExistingName" value="<?= $file_name->getName() ?>" />
			<?= $file_name->transform( [ 'width' => 265, 'height' => 205 ] )->toHtml() ?>
		</td>
	</tr>
</table>
<div style="text-align: center;"><a onclick="WMU_insertImage('overwrite');" href="#"><?= wfMessage( 'wmu-overwrite' )->escaped()  ?></a></div>
