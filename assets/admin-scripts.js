(function ($) {
    $(function() {

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


		const z_use_rate_limit_input = document.getElementById('z_use_rate_limit');
		const z_rate_limit_value_input = document.getElementById('z_rate_limit_value');

		function zUpdateRateLimitState() {
			z_rate_limit_value_input.disabled = !z_use_rate_limit_input.checked;
		}
		// Run once on load
		zUpdateRateLimitState();

		// And in case of changes
		z_use_rate_limit_input.addEventListener('change', zUpdateRateLimitState);

    });
})(jQuery);