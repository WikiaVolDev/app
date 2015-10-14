<?php
global $wgExtensionsPath, $wgBlankImgUrl;
?>

<h1><?= wfMessage( 'wmu-upload-image' )->escaped() ?></h1>


<div class="ImageUploadLeft">
	<div id="ImageUploadThumb"><?= $props['file']->transform( [ 'width' => min( $props['file']->getWidth(), 400 ) ] )->toHTML() ?></div>


	<div class="details">
		<div class="ImageUploadLeftMask"></div>

		<div style="position: relative; z-index: 2">
			<label><?= wfMessage( 'wmu-caption' )->escaped() ?></label>
			<textarea id="ImageUploadCaption"><?= isset( $props['default_caption'] ) ? $props['default_caption'] :
				'' ?></textarea>

			<a class="backbutton" href="#" style="display:none" ><?= wfMessage( 'wmu-back' )->escaped() ?></a>
			<input type="submit" value="<?= wfMessage( 'wmu-insert2' )->escaped() ?>" onclick="WMU_insertImage('details');" />
		</div>
	</div>
</div>


<div class="ImageUploadRight">
	<h2><?= wfMessage( 'wmu-appearance-in-article' )->escaped() ?></h2>


	<h3><?= wfMessage( 'wmu-layout' )->escaped() ?></h3>
	<span id="WMU_LayoutThumbBox">
		<label for="ImageUploadThumbOption"><input onclick="MWU_imageSizeChanged('thumb');" type="radio" name="fullthumb" id="ImageUploadThumbOption" checked=checked /><?= wfMessage( 'wmu-thumbnail' )->escaped() ?></label>
	&nbsp;
	</span>
	<span id="WMU_LayoutFullBox">
		<label for="ImageUploadFullOption"><input onclick="MWU_imageSizeChanged('full');" type="radio" name="fullthumb" id="ImageUploadFullOption" /> <?= wfMessage( 'wmu-fullsize', $props['file']->width, $props['file']->height )->parse() ?></label>
	</span>



	<div id="ImageWidthRow">
		<input type="hidden" name="ImageUploadWidthCheckbox" id="ImageUploadWidthCheckbox" value="false">
		<div id="ImageUploadSlider">
			<img src="<?= $wgExtensionsPath . '/wikia/WikiaMiniUpload/images/slider_thumb_bg.png' ?>" id="ImageUploadSliderThumb" />
		</div>
		<span id="ImageUploadInputWidth">
			<input type="text" id="ImageUploadManualWidth" name="ImageUploadManualWidth" value="" onchange="WMU_manualWidthInput(this)" onkeyup="WMU_manualWidthInput(this)" /> px
		</span>
	</div>



	<div id="ImageLayoutRow">
		<h3><?= wfMessage( 'wmu-alignment' )->escaped() ?></h3>
		<input type="radio" id="ImageUploadLayoutLeft" name="layout" />
		<label for="ImageUploadLayoutLeft"><img src="<?= $wgExtensionsPath . '/wikia/WikiaMiniUpload/images/image_upload_left.png' ?>" /></label>

		<input type="radio" id="ImageUploadLayoutRight" name="layout" checked="checked" />
		<label for="ImageUploadLayoutRight"><img src="<?= $wgExtensionsPath . '/wikia/WikiaMiniUpload/images/image_upload_right.png' ?>" /></label>
	</div>





	<div id="ImageLinkRow">
		<h3><?= wfMessage( 'wmu-link' )->escaped() ?></h3>
		<input id="ImageUploadLink" type="text" />
	</div>

	<?
	if ( isset( $props['name'] ) ) {
	?>

	<div class="advanced">
		<div id="NameRow">
			<h3><?= wfMessage( 'wmu-name' )->escaped() ?></h3>
			<input id="ImageUploadName" type="text" size="30" value="<?= $props['partname'] ?>" />
			<label for="ImageUploadName">.<?= $props['extension'] ?></label>
			<input id="ImageUploadExtension" type="hidden" value="<?= $props['extension'] ?>" />
			<input id="ImageUploadReplaceDefault" type="hidden" value="on" />
		</div>

		<div id="LicensingRow">
			<h3><?= wfMessage( 'wmu-licensing' )->escaped() ?></h3>
			<span id="ImageUploadLicenseSpan" >
			<?php
				$licenses = new Licenses( [ 'id' => 'ImageUploadLicense', 'name' => 'ImageUploadLicense', 'fieldname' => 'ImageUploadLicense' ] );
				echo $licenses->getInputHTML( null );
			?>
			</span>
			<div id="ImageUploadLicenseControl">
				<a id="ImageUploadLicenseLink" href="#" onclick="WMU_toggleLicenseMesg(event);" >[<?= wfMessage( 'wmu-hide-license-msg' )->escaped() ?>]</a>
			</div>
			<div id="ImageUploadLicenseTextWrapper">
				<div id="ImageUploadLicenseText">&nbsp;</div>
			</div>
		</div>
	</div>

	<img src="<?= $wgBlankImgUrl ?>" class="chevron"> <a href="#" id="WMU_showhide" class="show" data-more="<?= wfMessage( 'wmu-more-options' )->escaped() ?>" data-fewer="<?= wfMessage( 'wmu-fewer-options' )->escaped() ?>"><?= wfMessage( 'wmu-more-options' )->escaped() ?></a>

	<?
	} else if ( empty( $props['default_caption'] ) ) { ?>
		<input id="ImageUploadReplaceDefault" type="hidden" value="on" />
	<?
	} else {
	?>
	<h3><?= wfMessage( 'wmu-caption' )->escaped() ?></h3>
	<input id="ImageUploadReplaceDefault" type="checkbox"> <label for="ImageUploadReplaceDefault"><?= wfMessage
		( 'wmu-replace-default-caption' )->escaped() ?></label>
	<?
	}
	?>

	<input id="ImageUploadMWname" type="hidden" value="<?= urlencode( $props['mwname'] ) ?>" />
	<input id="ImageUploadTempid" type="hidden" value="<?= isset( $props['tempid'] ) ? $props['tempid'] : '' ?>" />
	<input id="ImageRealWidth" type="hidden" value="<?= $props['file']->getWidth() ?>" />
	<input id="ImageRealHeight" type="hidden" value="<?= $props['file']->getHeight() ?>" />


</div>
