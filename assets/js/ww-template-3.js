(function () {
	'use strict';

	function boot() {
		var root = document.querySelector('article.ww-project');
		if (!root) {
			return;
		}
		root.classList.add('ww-js');
		var buttons = root.querySelectorAll('.ww-project__toggle');
		if (!buttons.length) {
			return;
		}
		var desktop = window.matchMedia('(min-width: 900px)');

		function sync() {
			var wide = desktop.matches;
			Array.prototype.forEach.call(buttons, function (button) {
				var wrap = button.parentNode;
				if (wide) {
					button.setAttribute('aria-expanded', 'true');
					if (wrap && wrap.classList) {
						wrap.classList.add('is-open');
					}
					return;
				}
				if (!button.hasAttribute('data-ww-set')) {
					button.setAttribute('aria-expanded', 'false');
					if (wrap && wrap.classList) {
						wrap.classList.remove('is-open');
					}
					button.setAttribute('data-ww-set', '1');
				}
			});
		}

		Array.prototype.forEach.call(buttons, function (button) {
			button.addEventListener('click', function () {
				if (desktop.matches) {
					return;
				}
				var open = button.getAttribute('aria-expanded') === 'true';
				button.setAttribute('aria-expanded', open ? 'false' : 'true');
				if (button.parentNode && button.parentNode.classList) {
					button.parentNode.classList.toggle('is-open', !open);
				}
			});
		});

		sync();
		window.addEventListener('resize', sync, { passive: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
