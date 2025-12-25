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
    });
})(jQuery);