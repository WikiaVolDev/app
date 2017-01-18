/*global define*/
define('ext.wikia.adEngine.video.uapVideo', [
	'ext.wikia.adEngine.adHelper',
	'ext.wikia.adEngine.context.uapContext',
	'ext.wikia.adEngine.slot.adSlot',
	'ext.wikia.adEngine.video.player.porvata',
	'ext.wikia.adEngine.video.player.playwire',
	'ext.wikia.adEngine.video.player.ui.videoInterface',
	'ext.wikia.adEngine.video.player.uiTemplate',
	'wikia.document',
	'wikia.log',
	'wikia.window'
], function (adHelper, uapContext, adSlot, porvata, playwire, videoInterface, UITemplate, doc, log, win) {
	'use strict';

	var logGroup = 'ext.wikia.adEngine.video.uapVideo';

	function getVideoSize(slot, params) {
		var width = slot.clientWidth,
			slotHeight = width / params.aspectRatio;

		if (params.autoPlay) {
			width = params.videoAspectRatio * slotHeight;
		}

		return {
			width: width,
			height: width / params.videoAspectRatio
		};
	}

	function loadPorvata(params, slotContainer, providerContainer) {
		params.container = slotContainer;

		log(['VUAP loadPorvata', params], log.levels.debug, logGroup);

		return porvata.inject(params)
			.then(function (video) {
				var uiElements = params.autoPlay ? UITemplate.autoPlay : UITemplate.default;

				log(['VUAP UI elements', uiElements], log.levels.debug, logGroup);

				videoInterface.setup(video, uiElements, {
					image: providerContainer,
					container: slotContainer,
					aspectRatio: params.aspectRatio,
					videoAspectRatio: params.videoAspectRatio,
					videoPlaceholderElement: params.videoPlaceholderElement
				});

				video.addEventListener('allAdsCompleted', function () {
					video.reload();
				});

				return video;
			});
	}

	function loadPlaywire(params, adSlot, providerContainer) {
		var container = doc.createElement('div');

		container.classList.add('video-player', 'hidden');
		adSlot.appendChild(container);

		params.container = container;

		log(['VUAP loadPlaywire', params], log.levels.debug, logGroup);
		return playwire.inject(params)
			.then(function (video) {
				videoInterface.setup(video, [
					'closeButton',
					'toggleAnimation'
				], {
					image: providerContainer,
					container: adSlot,
					aspectRatio: params.aspectRatio,
					videoAspectRatio: params.videoAspectRatio
				});

				video.addEventListener('wikiaAdStarted', function () {
					var size = getVideoSize(adSlot, params);
					video.resize(size.width, size.height);
				});
				if (params.autoPlay) {
					var size = getVideoSize(adSlot, params);
					video.play(size.width, size.height);
				}

				return video;
			});
	}

	function getPercentageVideoHeight(size) {
		return 100 / (size.width / size.height);
	}

	function loadVideoAd(params) {
		var loadedPlayer,
			providerContainer = adSlot.getProviderContainer(params.slotName),
			videoContainer = providerContainer.parentNode,
			size;

		log(['loadVideoAd params', params], log.levels.debug, logGroup);

		size = getVideoSize(videoContainer, params);
		params.width = size.width;
		params.height = size.height;
		params.videoSize = getPercentageVideoHeight(size);
		params.vastTargeting = {
			src: params.src,
			pos: params.slotName,
			passback: 'vuap',
			uap: uapContext.getUapId()
		};

		log(['loadVideoAd upadated params', params], log.levels.debug, logGroup);

		if (params.player === 'playwire') {
			loadedPlayer = loadPlaywire(params, videoContainer, providerContainer);
		} else {
			loadedPlayer = loadPorvata(params, videoContainer, providerContainer);
		}

		return loadedPlayer.then(function (video) {
			win.addEventListener('resize', adHelper.throttle(function () {
				var size = getVideoSize(videoContainer, params);
				video.resize(size.width, size.height);
			}));

			params.videoTriggerElement.addEventListener('click', function () {
				video.play();
			});

			return video;
		});
	}

	/**
	 * Check if all required params are present
	 *
	 * @param params
	 * @returns bool
	 */
	function isEnabled(params) {
		return !!params.videoAspectRatio;
	}

	return {
		isEnabled: isEnabled,
		loadVideoAd: loadVideoAd
	};
});
