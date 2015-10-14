<div id="ImageUploadHeadline">
<div id="ImageUploadPagination">
<?php
if ( isset( $data['prev'] ) ) {
?>
	<a onclick="WMU_recentlyUploaded('offset=<?= $data['prev'] ?>', 'prev'); return false;" href="#"><?= wfMessage( 'wmu-prev' )->escaped() ?></a>
<?php
}
if ( isset( $data['prev'] ) && isset( $data['next'] ) ) {
?>
	|
<?php
}
if ( isset( $data['next'] ) ) {
?>
	<a onclick="WMU_recentlyUploaded( 'offset=<?= $data['next'] ?>', 'next' ); return false;" href="#"><?= wfMessage( 'wmu-next' )->escaped() ?></a>
<?php
}
?>
</div>
<?= wfMessage( 'wmu-recent-inf' )->escaped() ?>
</div>

<table cellspacing="0" id="ImageUploadFindTable">
	<tbody>
<?php
if ( $data['gallery'] instanceof WikiaPhotoGallery ) {
	$images = $data['gallery']->getImages();
	$imageTitles = [];

	for ( $j = 0; $j < ceil( count( $images ) / 4 ); $j++ ) {
?>
		<tr class="ImageUploadFindImages">
<?php
		for ( $i = $j * 4; $i < ( $j + 1 ) * 4; $i++ ) {
			if ( isset( $images[$i] ) ) {
				$file = wfLocalFile( $images[$i][0] );
				$imageTitles[$i] = $file;
?>
				<td><a href="#" alt="<?= addslashes( $file->getName() ) ?>" title="<?= addslashes( $file->getName() ) ?>" onclick="WMU_chooseImage('<?= urlencode( $file->getName() ) ?>'); return false;"><?= $file->transform(
				[ 'width' => 120, 'height' => 90 ] )->toHtml() ?></a></td>
<?php
			}
		}
?>
		</tr>
		<tr class="ImageUploadFindLinks">
<?php
		for ( $i = $j * 4; $i < ( $j + 1 ) * 4; $i++ ) {
			if ( isset( $imageTitles[$i] ) ) {
?>
				<td><a href="#" onclick="WMU_chooseImage('<?= urlencode( $imageTitles[$i]->getName() ) ?>'); return false;"><?= wfMessage( 'wmu-insert3' )->escaped() ?></a></td>
<?php
			}
		}
?>
		</tr>
<?php
	}
}
?>
	</tbody>
</table>
