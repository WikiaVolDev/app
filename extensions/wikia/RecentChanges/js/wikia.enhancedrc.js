/**
 * Adds an Expand all/Collapse all button to enhanced RecentChanges
 * @author TK-999
 *
 * @file
 * @ingroup extensions
 */
require(
	['jquery', 'mw'],
	function ($, mw)
{
	'use strict';

	/**
	 * Expand/collapse all block items
	 * @param {Object} event
	 */
	function toggleAll(event) {
		var $target = $(event.target),
			stateWas = $target.data('state');

		// Check if we should collapse or expand all
		// and update the state accordingly.
		if (stateWas === 'collapsed') {
			$('.rc-conntent .mw-collapsible-toggle-collapsed').click();
			$target.text(mw.message('rc-enhanced-collapse-all').text());
			$target.data('state', 'expanded');
		} else {
			$('.rc-conntent .mw-collapsible-toggle-expanded').click();
			$target.text(mw.message('rc-enhanced-expand-all').text());
			$target.data('state', 'collapsed');
		}
	}

	/**
	 * Add the expand/collapse button
	 */
	function init() {
		$(document.createElement('a'))
			.data('state', 'collapsed')
			.text(mw.message('rc-enhanced-expand-all').text())
			.attr({
				id: 'mw-enhanced-rc-toggle-all',
				role: 'button'
			})
			.on('click', toggleAll)
			.appendTo('.rc-conntent h4:first');
	}

	// Run setup on document.ready
	$(init);
});
