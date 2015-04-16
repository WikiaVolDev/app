<?php
if(!defined('MEDIAWIKI')) {exit(1);}

$wgAutoloadClasses['SpecialMakeMeStaff'] = __DIR__ . '/SpecialMakeMeStaff.php';
$wgExtensionMessagesFiles['MakeMeStaff'] = __DIR__ . '/MakeMeStaff.i18n.php';
$wgExtensionMessagesFiles['MakeMeStaffAlias'] = __DIR__ . '/MakeMeStaff.alias.php';
$wgSpecialPages['MakeMeStaff'] = 'SpecialMakeMeStaff';
?>
