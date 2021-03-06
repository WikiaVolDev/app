<?php
if ( !empty($err) ) {
        print $err;
}

echo wfMessage( 'specialcontact-intro-forget-account' )->parseAsBlock();
?>

<h2><?= wfMessage( 'specialcontact-title-forget-account' )->escaped() ?></h2>

<form id="contactform" method="post" action="">
<input name="wpEmail" type="hidden" value="<?= $encEmail ?>" />
<input name="wpUserName" type="hidden" value="<?= $encName ?>" />
<input name="wpEditToken" type="hidden" value="<?= Sanitizer::encodeAttribute( $editToken ) ?>" />

<? if (F::app()->wg->User->isLoggedIn()) {
	echo( wfMessage( 'specialcontact-logged-in-as', $encName )->parseAsBlock() );
	echo( wfMessage( 'specialcontact-mail-on-file', $encEmail )->parseAsBlock() );
} ?>
<p>
	<em>
		<?= wfMessage( 'specialcontact-label-forget-account-data-required-explanation' )->escaped() ?>
	</em>
</p>

<p>
	<label for="wpCouuntry"><?= wfMessage( 'specialcontact-label-forget-account-country-info' )->escaped() ?></label>
</p>
<p>
	<select name="wpCountry" required>
		<option selected disabled hidden style='display: none' value=''></option>
		<? foreach(SpecialContactCountryNames::getNames(F::app()->wg->Lang->getCode()) as $code => $country) {
			echo('<option value="'. $country . '">' . $country . '</option>');
		} ?>
	</select>
</p>

<p>
	<label for="wpFullName"><?= wfMessage( 'specialcontact-label-forget-account-full-name' )->escaped() ?></label>
</p>
<p>
	<input type="text" name="wpFullName" required />
</p>

<p>
	<label for="wpEmail"><?= wfMessage( 'specialcontact-label-forget-account-email-address' )->escaped() ?></label>
</p>
<p>
	<input type="email" name="wpEmail" required value="<?= $encEmail ?>" />
</p>

<p>
	<label for="wpIsForThemselves"><?= wfMessage( 'specialcontact-label-forget-account-is-on-behalf' )->escaped() ?></label>
</p>
<p>
	<label>
		<input type="radio" name="wpIsForThemselves" value="Yes" required checked />
		<?= wfMessage( 'swm-yes' )->escaped() ?>
	</label>
	<label>
		<input type="radio" name="wpIsForThemselves" value="No" />
		<?= wfMessage( 'swm-no' )->escaped() ?>
	</label>
</p>

<div class="wp-relationship-input-wrapper" style="display: none;">
	<p>
		<label for="wpRelationship"><?= wfMessage( 'specialcontact-label-forget-account-relationship' )->escaped() ?></label>
	</p>
	<p>
		<input type="text" name="wpRelationship" required disabled />
	</p>
	<p>
		<label for="wpUsernameOnBehalf"><?= wfMessage( 'specialcontact-label-forget-account-username-on-behalf' )->escaped() ?></label>
	</p>
	<p>
		<input type="text" name="wpUsernameOnBehalf" required disabled />
	</p>
</div>

<p>
	<label for="wpPreviousRequest"><?= wfMessage( 'specialcontact-label-forget-account-previous-request' )->escaped() ?></label>
</p>
<p>
	<label>
		<input type="radio" name="wpPreviousRequest" value="Yes" required />
		<?= wfMessage( 'swm-yes' )->escaped() ?>
	</label>
	<label>
		<input type="radio" name="wpPreviousRequest" value="No" checked />
		<?= wfMessage( 'swm-no' )->escaped() ?>
	</label>
</p>

<p>
	<input type="checkbox" name="wpProcessingConsent" required />
	<label for="wpProcessingConsent"><?= wfMessage( 'specialcontact-label-forget-account-processing-consent' )->escaped() ?></label>
</p>

<p>
	<?= wfMessage( 'specialcontact-label-forget-account-data-processing-info' )->escaped() ?>
</p>

<p>
	<input type="checkbox" name="wpConfirm" required />
	<label for="wpConfirm"><?= wfMessage( 'specialcontact-label-forget-account-confirm-checkbox' )->escaped() ?></label>
</p>

<p>
	<?= wfMessage( 'specialcontact-label-forget-account-data-deletion-info' )->escaped() ?>
</p>

<input type="submit" value="<?= wfMessage( 'specialcontact-label-forget-account-confirm' )->escaped() ?>" />

<input type="hidden" id="wpBrowser" name="wpBrowser" value="<?= Sanitizer::encodeAttribute( $_SERVER['HTTP_USER_AGENT'] ); ?>" />
<input type="hidden" id="wpAbTesting" name="wpAbTesting" value="[unknown]" />
</form>
