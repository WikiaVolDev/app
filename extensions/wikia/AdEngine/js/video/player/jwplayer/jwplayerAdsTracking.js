/*global define*/
define('ext.wikia.adEngine.video.player.jwplayer.adsTracking', [
	'ext.wikia.adEngine.utils.eventDispatcher',
	'ext.wikia.adEngine.video.player.jwplayer.jwplayerTracker',
	'ext.wikia.adEngine.video.vastParser'
], function (eventDispatcher, tracker, vastParser) {
	'use strict';

	function clearParams(params) {
		params.lineItemId = undefined;
		params.creativeId = undefined;
		params.contentType = undefined;
	}

	function dispatchStatus(vastUrl, adInfo, status) {
		if (vastUrl.indexOf('https://pubads.g.doubleclick.net') === -1) {
			return;
		}
		if (!eventDispatcher) {
			return;
		}

		eventDispatcher.dispatch('adengine.video.status', {
			vastUrl: vastUrl,
			creativeId: adInfo.creativeId,
			lineItemId: adInfo.lineItemId,
			status: status
		});
	}

	return function(player, params) {
		tracker.track(params, 'init');

		player.on('adComplete', function () {
			clearParams(params);
		});

		player.on('adError', function (event) {
			dispatchStatus(event.tag, params, 'error');
			clearParams(params);
		});

		player.on('adRequest', function (event) {
			var currentAd = vastParser.getAdInfo(event.ima && event.ima.ad);

			params.lineItemId = currentAd.lineItemId;
			params.creativeId = currentAd.creativeId;
			params.contentType = currentAd.contentType;
		});

		player.on('adImpression', function (event) {
			dispatchStatus(event.tag, params, 'success');
		});

		tracker.register(player, params);
	};
});
