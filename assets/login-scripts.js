(function ($) {
	document.addEventListener('DOMContentLoaded', function () {
		var nav = document.getElementById('nav');
		if (nav) {
			var p = document.createElement('p');
			p.id = 'zodanloginonce-request-link';
			p.innerHTML = '<a href="'+zOnetimeLoginLinkVars.requestLinkURL+'">'+zOnetimeLoginLinkVars.requestLinkText+'</a>';
			nav.insertAdjacentElement('afterend', p);
		}
	});
})(jQuery);