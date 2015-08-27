<?php
/**
 * SpotifyTag
 *
 * Creates the <spotify> tag
 *
 * @author TyA <tyler@faceyspacies.com>
 *
 */
 
$wgExtensionCredits['other'][] = [
	'name' => 'SpotifyTag',
	'version' => '1.0',
	'url' => 'https://github.com/Wikia/app/tree/dev/extensions/wikia/SpotifyTag',
	'author' => '[http://community.wikia.com/wiki/User:TyA TyA]',
	'descriptionmsg' => 'spotifytag-desc',
];
 
$wgAutoloadClasses['SpotifyTagHooks'] = __DIR__ . '/SpotifyTagHooks.class.php';

$wgExtensionMessagesFiles['SpotifyTag'] = __DIR__ . '/SpotifyTag.i18n.php';

$wgHooks['ParserFirstCallInit'][] = 'SpotifyTagHooks::onParserFirstCallInit';
