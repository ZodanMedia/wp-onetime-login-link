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
		if( z_use_rate_limit_input.checked) {
			z_rate_limit_value_input.removeAttribute('disabled');
		} else {
			z_rate_limit_value_input.setAttribute('disabled', 'disabled');
		}
		z_use_rate_limit_input.addEventListener('click', function(){
			if(z_use_rate_limit_input.checked) {
				z_rate_limit_value_input.removeAttribute('disabled');
			} else {
				z_rate_limit_value_input.setAttribute('disabled', 'disabled');
			}
		});

    });
})(jQuery);