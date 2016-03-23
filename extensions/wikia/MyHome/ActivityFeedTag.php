<?php

$wgExtensionCredits['parserhook'][] = [
	'path' => __FILE__,
	'name' => 'ActivityFeedTag',
	'author' => [ 'Inez Korczyński', '[http://www.wikia.com/wiki/User:Marooned Maciej Błaszkowski (Marooned)]' ],
	'version' => '1.0',
	'description' => 'Provides wiki activity data'
];

$wgHooks['ParserFirstCallInit'][] = 'ActivityFeedTag_setup';

/**
 * @param Parser $parser
 * @return bool true
 * @throws MWException
 */
function ActivityFeedTag_setup( Parser $parser ) {
	$parser->setHook( 'activityfeed', 'ActivityFeedTag_render' );
	return true;
}

/**
 * @param string $content
 * @param array $attributes
 * @param Parser $parser
 * @param PPFrame $frame
 * @return string
 * @throws WikiaException
 */
function ActivityFeedTag_render( $content, array $attributes, Parser $parser, PPFrame $frame ) {
	$wg = F::app()->wg;

	if ( !class_exists( 'ActivityFeedHelper' ) ) {
		return '';
	}

	$parameters = ActivityFeedHelper::parseParameters( $attributes );

	$tagid = str_replace( '.', '_', uniqid( 'activitytag_', true ) );    //jQuery might have a problem with . in ID
	$jsParams = "size={$parameters['maxElements']}";
	if ( !empty( $parameters['includeNamespaces'] ) ) $jsParams .= "&ns={$parameters['includeNamespaces']}";
	if ( !empty( $parameters['flags'] ) ) $jsParams .= '&flags=' . implode( '|', $parameters['flags'] );
	$parameters['tagid'] = $tagid;

	$feedHTML = ActivityFeedHelper::getList( $parameters );

	$style = empty( $parameters['style'] ) ? '' : ' style="' . $parameters['style'] . '"';
	$timestamp = wfTimestampNow();

	$snippetsDependencies = [ '/extensions/wikia/MyHome/ActivityFeedTag.js', '/extensions/wikia/MyHome/ActivityFeedTag.css' ];

	if ( $wg->EnableAchievementsInActivityFeed && $wg->EnableAchievementsExt ){
		array_push( $snippetsDependencies, '/extensions/wikia/AchievementsII/css/achievements_sidebar.css' );
	}

	$snippets = JSSnippets::addToStack(
		$snippetsDependencies,
		null,
		'ActivityFeedTag.initActivityTag',
		[
			'tagid' => $tagid,
			'jsParams' => $jsParams,
			'timestamp' => $timestamp
		]
	);

	return "<div$style>$feedHTML</div>$snippets";
}
