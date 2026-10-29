/**
 * media-band — plays the YouTube video in place.
 *
 * The poster is a link to the video on YouTube. A click swaps the link for the
 * player (youtube-nocookie, autoplay), so nothing from YouTube loads until the
 * visitor asks for it. Delegated, so it covers every band on the page.
 */
(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var link = event.target && event.target.closest ? event.target.closest('a[data-ap-video]') : null;
		if (!link) {
			return;
		}
		var id = link.getAttribute('data-ap-video') || '';
		if (!/^[A-Za-z0-9_-]{11}$/.test(id)) {
			return;
		}
		event.preventDefault();
		var frame = document.createElement('iframe');
		frame.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
		frame.title = link.getAttribute('aria-label') || 'Video';
		frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
		frame.allowFullscreen = true;
		frame.className = 'ap-media-band__frame';
		var box = link.parentNode;
		box.classList.add('is-playing');
		box.replaceChild(frame, link);
		frame.focus();
	});
})();
