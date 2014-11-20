/* global require */
require([
	'jquery', 'wikia.window', 'wikia.articleNavUserTools',  'wikia.venusToc'
], function ($, win, userTools, tocModule) {
	'use strict';

	var isTouchScreen = win.Wikia.isTouchScreen(),
		$tocButton = $('#articleNavToc'),
		$navButtons = $('.article-navigation').find('.nav-icon');

	/**
	 * @desc handler that initialises TOC
	 * @param {Event} event
	 */
	function initTOChandler(event) {
		event.stopPropagation();
		tocModule.init(event.target.id, isTouchScreen);
	}

	//Handle clicking (tapping) in icons on touch devices - turn other icons "active" state off
	if (isTouchScreen) {
		$navButtons.on('click', function (e) {
			var eTarget = e.target;

			$navButtons.each(function () {
				if (this !== eTarget) {
					eTarget.classList.remove('active');
				}
			});
		});
	}

	//Initialize user tools
	userTools.init();

	// initialize TOC in left navigation on first hover / click (touch device)
	// only if there are sections from which ToC is built
	if (tocModule.isEnabled()) {
		$tocButton.show().one(isTouchScreen ? 'click' : 'mouseenter', initTOChandler);
	}
});
