<?php

/**
 * RecentChanges
 * @author Kyle Florence, Saipetch Kongkatong, Tomasz Odrobny
 */

$wgExtensionCredits['other'][] = [
	'name' => 'RecentChanges',
	'author' => [ 'Kyle Florence', 'Saipetch Kongkatong', 'Tomasz Odrobny' ],
	'descriptionmsg' => 'recentchanges-desc',
	'url' => 'https://github.com/Wikia/app/tree/dev/extensions/wikia/RecentChanges'
];

$dir = __DIR__ . '/';

//classes
$wgAutoloadClasses['RecentChangesController'] =  $dir . 'RecentChangesController.class.php';
$wgAutoloadClasses['RecentChangesHooks'] =  $dir . 'RecentChangesHooks.class.php';
$wgAutoloadClasses['RecentChangesFiltersStorage'] =  $dir . 'RecentChangesFiltersStorage.class.php';

// Hooks
$wgHooks['onGetNamespaceCheckbox'][] = 'RecentChangesHooks::onGetNamespaceCheckbox';
$wgHooks['SpecialRecentChangesQuery'][] = 'RecentChangesHooks::onGetRecentChangeQuery';
$wgHooks['FetchChangesList'][] = 'RecentChangesHooks::onFetchChangesList';

// i18n
$wgExtensionMessagesFiles['RecentChanges'] = $dir . 'RecentChanges.i18n.php';

// ResourceLoader module for enhanced RC
$wgResourceModules['wikia.enhancedrc'] = [
	'scripts' => [
		'js/wikia.enhancedrc.js'
	],
	'styles' => [
		'css/wikia.enhancedrc.css'
	],
	'messages' => [
		'rc-enhanced-expand-all',
		'rc-enhanced-collapse-all'
	],
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'wikia/RecentChanges'
];
