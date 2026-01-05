    document.addEventListener('DOMContentLoaded', function () {

		/**
		 * Show a preview of the link text (for mail) in the settings screen
		 * 
		 */
		const mail_linktext = document.getElementById('zmail-linktext');
		const zlinktextExample = document.getElementById('zlinktext-example');
		const zlinktextExampleFrame = document.getElementById('zlinktext-example-frame');
		if( mail_linktext ) {
			if( mail_linktext.value.length > 0 ) {
				zlinktextExampleFrame.classList.add('show');
				zlinktextExample.innerHTML = mail_linktext.value;
			}
			mail_linktext.addEventListener('keyup', () => {
				if( mail_linktext.value.length > 0 ) {
					zlinktextExampleFrame.classList.add('show');
					zlinktextExample.innerHTML = mail_linktext.value;
				} else {
					zlinktextExampleFrame.classList.remove('show');
				}
			});
		}

		/**
		 * Enable/disable the rate limit input
		 * 
		 */

		const z_use_rate_limit_input = document.getElementById('z_use_rate_limit');
		const z_rate_limit_value_input = document.getElementById('z_rate_limit_value');

		function zUpdateRateLimitState() {
			z_rate_limit_value_input.disabled = !z_use_rate_limit_input.checked;
		}

		if ( z_use_rate_limit_input && z_rate_limit_value_input ) {
			// Run once on load
			zUpdateRateLimitState();
			// And in case of changes
			z_use_rate_limit_input.addEventListener('change', zUpdateRateLimitState);
		}

	});




// $(selector).attrchange(): a call back function for when an attribute changes
//
// can return attribute changes for selector
(function($) {
	var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;

	$.fn.attrchange = function(callback) {
		if (MutationObserver) {
			var options = {
				subtree: false,
				attributes: true
			};

			var observer = new MutationObserver(function(mutations) {
				mutations.forEach(function(e) {
					callback.call(e.target, e.attributeName);
				});
			});

			return this.each(function() {
				observer.observe(this, options);
			});

		}
	}

	// when one details opens, close the others
	$('.zodan-settings-details-wrapper > details').attrchange(function(attribute){
	if(attribute == "open" && $(this).attr("open")) {
		$(this).siblings("details").removeAttr("open");
	}
	});

	// keyboard: prevent closing the open details to emulate tabs
	$('.zodan-settings-details-wrapper > details > summary').on("keydown", function(e) {
	if(e.keyCode == 32 || e.keyCode == 13) {
		if($(this).parent().attr("open")) {
		e.preventDefault();
		}
	} 
	});

	// mouse: prevent closing the open details to emulate tabs
	$('.zodan-settings-details-wrapper > details > summary').on("click", function(e) {
	if($(this).parent().attr("open")) {
		e.preventDefault();
	}
	});
})(jQuery);