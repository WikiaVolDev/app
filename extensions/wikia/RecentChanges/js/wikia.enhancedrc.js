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
			$('.rc-conntent .mw-collapsible-toggle').click();

			// Check if we collapsed or expanded all
			// and update the state accordingly.
			if (stateWas === 'collapsed') {
				$target.html(mw.message('rc-enhanced-collapse-all').escaped());
				$target.data('state', 'expanded');
			} else {
				$target.html(mw.message('rc-enhanced-expand-all').escaped());
				$target.data('state', 'collapsed');
			}
	}

	/**
	 * Add the expand/collapse button
	 */
	function init() {
		$(document.createElement('a'))
			.data('state', 'collapsed')
			.html(mw.message('rc-enhanced-expand-all').escaped())
			.attr({ id: 'mw-enhanced-rc-toggle-all' })
			.on('click', toggleAll)
			.appendTo('.rc-conntent h4:first');
	}

	// Run setup on document.ready
	$(init);
});
