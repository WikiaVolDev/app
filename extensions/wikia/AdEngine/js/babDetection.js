/*global define*/
define('ext.wikia.adEngine.babDetection', [
	'wikia.document',
	'wikia.log',
	'wikia.tracker',
	'wikia.window'
], function (doc, log, tracker, win) {
	'use strict';

	var logGroup = 'ext.wikia.adEngine.babDetection';

	function dispatchDetectionEvent(eventName) {
		var event = doc.createEvent('Event');
		event.initEvent(eventName, true, false);

		doc.dispatchEvent(event);
	}

	function setRuntimeParams(isAdBlockDetected) {
		win.ads.runtime = win.ads.runtime || {};
		win.ads.runtime.bab = win.ads.runtime.bab || {};
		win.ads.runtime.bab.blocking = isAdBlockDetected;
	}

	function initDetection(isAdBlockDetected) {
		var eventName = isAdBlockDetected ? 'bab.blocking' : 'bab.not_blocking';

		log(['BlockAdBlock detection, adBlock detected:', isAdBlockDetected], 'debug', logGroup);

		setRuntimeParams(isAdBlockDetected);
		dispatchDetectionEvent(eventName);

		tracker.track({
			category: 'ads-babdetector-detection',
			action: 'impression',
			label: isAdBlockDetected ? 'Yes' : 'No',
			value: 0,
			trackingMethod: 'internal'
		});
	}

	return {
		initDetection: initDetection
	};
});
