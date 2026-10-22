(function (wp, $) {
	'use strict';

	var i18n = wp.i18n || {};
	var __ = i18n.__ || function (text) { return text; };

	function panel(template) {
		return document.querySelector('.ww-template-panel[data-template="' + template + '"]');
	}

	function currentTemplate() {
		var checked = document.querySelector('input[name="ww_template"]:checked');
		return checked ? checked.value : '';
	}

	function reindex(list) {
		var key = list.getAttribute('data-repeater');
		$(list).children('.ww-repeater__row').each(function (index) {
			$(this).find('[name]').each(function () {
				this.name = this.name.replace(/ww\[[^\]]+\]\[(?:\d+|__i__)\]/, 'ww[' + key + '][' + index + ']');
			});
		});
	}

	function imageCount(list) {
		var count = 0;
		$(list).children('.ww-repeater__row').each(function () {
			var id = $(this).find('input[type="hidden"]').first().val();
			if (id && id !== '0') {
				count += 1;
			}
		});
		return count;
	}

	function warn(list) {
		var note = $(list).closest('.ww-repeater').find('.ww-soft-warning').first();
		if (!note.length) {
			return;
		}
		var count = imageCount(list);
		var exact = list.getAttribute('data-exact');
		var min = parseInt(list.getAttribute('data-min'), 10);
		var max = parseInt(list.getAttribute('data-max'), 10);
		var bad = false;
		var expected = '';
		if (exact) {
			bad = count > 0 && count !== parseInt(exact, 10);
			expected = exact;
		} else if (min && max) {
			bad = count > 0 && (count < min || count > max);
			expected = min + '\u2013' + max;
		}
		if (!bad) {
			note.attr('hidden', 'hidden').text('');
			return;
		}
		note.removeAttr('hidden').text(
			__('This template is written for %1$s images. This one has %2$s.', 'ww-projects')
				.replace('%1$s', expected)
				.replace('%2$s', String(count))
		);
	}

	function bindSortable(list) {
		var $list = $(list);
		if ($list.data('ui-sortable')) {
			$list.sortable('destroy');
		}
		$list.sortable({
			handle: '.ww-repeater__handle',
			items: '> .ww-repeater__row',
			update: function () {
				reindex(list);
				warn(list);
			}
		});
	}

	function setPanelState(node, on) {
		if (!node) {
			return;
		}
		node.hidden = !on;
		node.querySelectorAll('input, textarea, select').forEach(function (el) {
			if (el.closest('.wp-editor-wrap')) {
				return;
			}
			el.disabled = !on;
		});
	}

	var richFields = ['description', 'content_block'];

	function editorId(template, field) {
		return 'ww_t' + template + '_' + field;
	}

	function isSectionVisible(textarea) {
		if (!textarea || !textarea.isConnected || textarea.disabled) {
			return false;
		}
		if (textarea.closest('[hidden]')) {
			return false;
		}
		var details = textarea.closest('details');
		if (details && !details.open) {
			return false;
		}
		var node = textarea;
		while (node && node !== document.body) {
			var style = window.getComputedStyle(node);
			if (style.display === 'none' || style.visibility === 'hidden') {
				return false;
			}
			node = node.parentElement;
		}
		var rect = textarea.getBoundingClientRect();
		return rect.width > 2 && rect.height > 2;
	}

	function initOne(textarea) {
		if (!textarea || !window.wp || !wp.editor || !wp.editor.initialize || !wp.editor.getDefaultSettings) {
			return false;
		}
		if (document.getElementById('wp-' + textarea.id + '-wrap')) {
			textarea.dataset.wwInit = '1';
			return true;
		}
		if (textarea.dataset.wwInit === '1' || !isSectionVisible(textarea)) {
			return textarea.dataset.wwInit === '1';
		}
		var defaults = wp.editor.getDefaultSettings();
		var tinymceSettings = Object.assign({}, defaults.tinymce || {}, {
			height: 220,
			wp_autoresize_on: false
		});
		if (window.tinyMCEPreInit) {
			window.tinyMCEPreInit.mceInit = window.tinyMCEPreInit.mceInit || {};
			window.tinyMCEPreInit.mceInit[textarea.id] = Object.assign({ selector: '#' + textarea.id }, tinymceSettings);
		}
		wp.editor.initialize(textarea.id, {
			tinymce: tinymceSettings,
			quicktags: true,
			mediaButtons: true
		});
		textarea.dataset.wwInit = '1';
		return true;
	}

	function watchUntilVisible(textarea) {
		if (!textarea || textarea.dataset.wwWatch === '1' || textarea.dataset.wwInit === '1') {
			return;
		}
		textarea.dataset.wwWatch = '1';
		var stop = function () {
			delete textarea.dataset.wwWatch;
		};
		var finish = function (observer, timer) {
			if (observer) {
				observer.disconnect();
			}
			if (timer) {
				window.clearInterval(timer);
			}
			stop();
		};
		var observer = null;
		var timer = window.setInterval(function () {
			if (!textarea.isConnected) {
				finish(observer, timer);
				return;
			}
			if (initOne(textarea)) {
				finish(observer, timer);
			}
		}, 250);
		window.setTimeout(function () {
			window.clearInterval(timer);
		}, 15000);
		if (!window.IntersectionObserver) {
			return;
		}
		observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting && initOne(textarea)) {
					finish(observer, timer);
				}
			});
		});
		observer.observe(textarea);
	}

	function scheduleEditors(root) {
		if (!root) {
			return;
		}
		root.querySelectorAll('textarea.ww-richtext').forEach(function (textarea) {
			var details = textarea.closest('details');
			if ((details && !details.open) || textarea.closest('[hidden]') || textarea.disabled) {
				return;
			}
			if (isSectionVisible(textarea)) {
				initOne(textarea);
			} else {
				watchUntilVisible(textarea);
			}
		});
	}

	function releaseEditor(id) {
		var textarea = document.getElementById(id);
		if (textarea) {
			delete textarea.dataset.wwInit;
			delete textarea.dataset.wwWatch;
		}
		if (window.wp && wp.editor && wp.editor.remove) {
			wp.editor.remove(id);
		}
		if (window.tinyMCEPreInit) {
			if (window.tinyMCEPreInit.mceInit) {
				delete window.tinyMCEPreInit.mceInit[id];
			}
			if (window.tinyMCEPreInit.qtInit) {
				delete window.tinyMCEPreInit.qtInit[id];
			}
		}
	}

	function releaseEditors(root) {
		if (!root) {
			return;
		}
		root.querySelectorAll('textarea.ww-richtext').forEach(function (textarea) {
			releaseEditor(textarea.id);
		});
	}

	function richValue(textarea) {
		if (!textarea) {
			return '';
		}
		var editor = window.tinymce && window.tinymce.get(textarea.id);
		if (editor && !editor.isHidden()) {
			editor.save();
			return editor.getContent();
		}
		return textarea.value;
	}

	function copyRichText(fromTemplate, toTemplate) {
		richFields.forEach(function (field) {
			var from = document.getElementById(editorId(fromTemplate, field));
			var to = document.getElementById(editorId(toTemplate, field));
			if (!from || !to) {
				return;
			}
			to.value = richValue(from);
		});
	}

	function syncScalars(from, to) {
		var groups = {};
		from.querySelectorAll('input[name], textarea[name], select[name]').forEach(function (el) {
			if (el.closest('.ww-repeater') || el.classList.contains('ww-richtext')) {
				return;
			}
			if (!groups[el.name]) {
				groups[el.name] = [];
			}
			groups[el.name].push(el);
		});
		Object.keys(groups).forEach(function (name) {
			var dests = to.querySelectorAll('[name="' + CSS.escape(name) + '"]');
			groups[name].forEach(function (el, index) {
				var dest = dests[index];
				if (!dest || dest.closest('.ww-repeater')) {
					return;
				}
				if (el.type === 'checkbox' || el.type === 'radio') {
					dest.checked = el.checked;
				} else {
					dest.value = el.value;
				}
			});
		});
	}

	function persistValues(list) {
		list.querySelectorAll('input, textarea, select').forEach(function (el) {
			if (el.type === 'checkbox' || el.type === 'radio') {
				if (el.checked) {
					el.setAttribute('checked', 'checked');
				} else {
					el.removeAttribute('checked');
				}
				return;
			}
			if (el.tagName === 'SELECT') {
				Array.prototype.forEach.call(el.options, function (option) {
					if (option.value === el.value) {
						option.setAttribute('selected', 'selected');
					} else {
						option.removeAttribute('selected');
					}
				});
				return;
			}
			el.setAttribute('value', el.value);
			if (el.tagName === 'TEXTAREA') {
				el.textContent = el.value;
			}
		});
	}

	function syncRepeaters(from, to) {
		from.querySelectorAll('.ww-repeater__list').forEach(function (list) {
			var dest = to.querySelector('.ww-repeater__list[data-repeater="' + list.getAttribute('data-repeater') + '"]');
			if (!dest) {
				return;
			}
			persistValues(list);
			dest.innerHTML = list.innerHTML;
			reindex(dest);
			bindSortable(dest);
			warn(dest);
		});
	}

	function showUnused(template) {
		var note = document.querySelector('.ww-unused');
		if (!note || !window.wwAdmin) {
			return;
		}
		var used = wwAdmin.templates[template] || [];
		var names = [];
		Object.keys(wwAdmin.stored || {}).forEach(function (key) {
			if (used.indexOf(key) === -1) {
				names.push(wwAdmin.stored[key]);
			}
		});
		if (!names.length) {
			note.hidden = true;
			note.textContent = '';
			return;
		}
		note.hidden = false;
		note.textContent = __('Saved data kept, but not shown on this template: %s.', 'ww-projects').replace('%s', names.join(', '));
	}

	function moveTerms(fromPanel, toPanel) {
		if (!fromPanel || !toPanel || fromPanel === toPanel) {
			return;
		}
		['location', 'markets_services'].forEach(function (name) {
			var fromBody = fromPanel.querySelector('[data-block="' + name + '"] .ww-block__body');
			var toBody = toPanel.querySelector('[data-block="' + name + '"] .ww-block__body');
			if (!fromBody || !toBody) {
				return;
			}
			var help = toBody.querySelector('.ww-block__help');
			Array.prototype.slice.call(fromBody.querySelectorAll(':scope > .ww-terms')).forEach(function (node) {
				if (help) {
					toBody.insertBefore(node, help);
				} else {
					toBody.appendChild(node);
				}
			});
		});
	}

	function activate(template, previous) {
		var next = panel(template);
		var prev = previous ? panel(previous) : null;
		if (prev && next && prev !== next) {
			copyRichText(previous, template);
			releaseEditors(prev);
			syncScalars(prev, next);
			syncRepeaters(prev, next);
			moveTerms(prev, next);
		}
		document.querySelectorAll('.ww-template-panel').forEach(function (node) {
			setPanelState(node, node.getAttribute('data-template') === String(template));
		});
		scheduleEditors(next);
		if (next) {
			next.querySelectorAll('.ww-repeater__list').forEach(function (list) {
				bindSortable(list);
				warn(list);
			});
		}
		showUnused(template);
		var picker = document.querySelector('.ww-picker');
		if (picker) {
			picker.setAttribute('data-current', template);
		}
	}

	function addRow(button) {
		var repeater = button.closest('.ww-repeater');
		var list = repeater.querySelector('.ww-repeater__list');
		var kind = repeater.getAttribute('data-kind');
		var template = document.getElementById(kind === 'contact' ? 'ww-row-contact' : 'ww-row-image');
		if (!template || !list) {
			return;
		}
		var row = template.content.firstElementChild.cloneNode(true);
		var key = list.getAttribute('data-repeater');
		row.querySelectorAll('[name]').forEach(function (el) {
			el.disabled = false;
			el.name = el.name.replace(/ww\[[^\]]+\]/, 'ww[' + key + ']');
		});
		list.appendChild(row);
		reindex(list);
		bindSortable(list);
		warn(list);
	}

	function openMedia(button) {
		var image = button.closest('.ww-image');
		if (!image || !wp.media) {
			return;
		}
		var frame = wp.media({
			title: __('Select image', 'ww-projects'),
			button: { text: __('Use image', 'ww-projects') },
			multiple: false,
			library: { type: 'image' }
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var idInput = image.querySelector('input[type="hidden"]');
			var preview = image.querySelector('.ww-image__preview');
			idInput.value = attachment.id;
			var src = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
			preview.innerHTML = '<img src="' + src + '" alt="">';
			var list = image.closest('.ww-repeater__list');
			if (list) {
				warn(list);
			}
		});
		frame.open();
	}

	function searchPeople(input) {
		var row = input.closest('.ww-contact');
		var results = row.querySelector('.ww-contact__results');
		var query = input.value.trim();
		window.clearTimeout(input.wwTimer);
		if (query.length < 2) {
			results.hidden = true;
			results.innerHTML = '';
			return;
		}
		input.wwTimer = window.setTimeout(function () {
			var base = (window.wwAdmin && wwAdmin.people) ? wwAdmin.people : 'leadership';
			wp.apiFetch({
				path: '/wp/v2/' + base + '?search=' + encodeURIComponent(query) + '&per_page=10&_fields=id,title'
			}).then(function (posts) {
				results.innerHTML = '';
				if (!posts || !posts.length) {
					results.hidden = false;
					results.innerHTML = '<li>' + __('No people found.', 'ww-projects') + '</li>';
					return;
				}
				posts.forEach(function (post) {
					var item = document.createElement('li');
					var choice = document.createElement('button');
					choice.type = 'button';
					choice.className = 'button-link ww-contact__choice';
					choice.textContent = post.title && post.title.rendered ? post.title.rendered : ('#' + post.id);
					choice.setAttribute('data-id', String(post.id));
					item.appendChild(choice);
					results.appendChild(item);
				});
				results.hidden = false;
			}).catch(function () {
				results.hidden = false;
				results.innerHTML = '<li>' + __('People search is unavailable. Use plain text.', 'ww-projects') + '</li>';
			});
		}, 250);
	}

	function locationLists() {
		return document.querySelectorAll('.ww-location-list');
	}

	function insertLocation(id, name) {
		locationLists().forEach(function (list) {
			var existing = list.querySelector('input[type="checkbox"][value="' + id + '"]');
			if (existing) {
				existing.checked = true;
				return;
			}
			var item = document.createElement('li');
			var label = document.createElement('label');
			label.className = 'selectit ww-terms__choice';
			var input = document.createElement('input');
			input.type = 'checkbox';
			input.name = 'ww[locations][]';
			input.value = String(id);
			input.checked = true;
			var panel = list.closest('.ww-template-panel');
			if (panel && panel.hidden) {
				input.disabled = true;
			}
			label.appendChild(input);
			label.appendChild(document.createTextNode(' ' + name));
			item.appendChild(label);
			var placed = false;
			var items = list.querySelectorAll(':scope > li');
			for (var i = 0; i < items.length; i++) {
				var text = items[i].textContent.replace(/\s+/g, ' ').trim();
				if (name.localeCompare(text, undefined, { sensitivity: 'base' }) < 0) {
					list.insertBefore(item, items[i]);
					placed = true;
					break;
				}
			}
			if (!placed) {
				list.appendChild(item);
			}
		});
	}

	function addLocation(input) {
		var wrap = input.closest('.ww-terms--location');
		var button = wrap ? wrap.querySelector('.ww-location-add__button') : null;
		var error = wrap ? wrap.querySelector('.ww-location-add__error') : null;
		var name = input.value.replace(/\s+/g, ' ').trim();
		if (!name || !wwAdmin || !wwAdmin.locationNonce) {
			return;
		}
		if (button) {
			button.disabled = true;
		}
		if (error) {
			error.hidden = true;
			error.textContent = '';
		}
		$.post(window.ajaxurl, {
			action: 'ww_add_location',
			nonce: wwAdmin.locationNonce,
			name: name
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				var message = response && response.data && response.data.message ? response.data.message : __('The location could not be added.', 'ww-projects');
				if (error) {
					error.hidden = false;
					error.textContent = message;
				}
				return;
			}
			insertLocation(response.data.id, response.data.name);
			document.querySelectorAll('.ww-location-add__name').forEach(function (field) {
				field.value = '';
			});
		}).fail(function (xhr) {
			var message = __('The location could not be added.', 'ww-projects');
			if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				message = xhr.responseJSON.data.message;
			}
			if (error) {
				error.hidden = false;
				error.textContent = message;
			}
		}).always(function () {
			if (button) {
				button.disabled = false;
			}
		});
	}

	function onClick(event) {
		var locationAdd = event.target.closest('.ww-location-add__button');
		if (locationAdd) {
			event.preventDefault();
			var locationInput = locationAdd.parentNode.querySelector('.ww-location-add__name');
			if (locationInput) {
				addLocation(locationInput);
			}
			return;
		}
		var add = event.target.closest('.ww-repeater__add');
		if (add) {
			event.preventDefault();
			addRow(add);
			return;
		}
		var remove = event.target.closest('.ww-repeater__remove');
		if (remove) {
			event.preventDefault();
			var list = remove.closest('.ww-repeater__list');
			var row = remove.closest('.ww-repeater__row');
			if (row) {
				row.remove();
			}
			if (list) {
				reindex(list);
				warn(list);
			}
			return;
		}
		var clear = event.target.closest('.ww-image__clear');
		if (clear) {
			event.preventDefault();
			var image = clear.closest('.ww-image');
			image.querySelector('input[type="hidden"]').value = '0';
			image.querySelector('.ww-image__preview').innerHTML = '';
			image.querySelectorAll('input[type="text"]').forEach(function (el) {
				el.value = '';
			});
			return;
		}
		var select = event.target.closest('.ww-image__select');
		if (select) {
			event.preventDefault();
			openMedia(select);
			return;
		}
		var tab = event.target.closest('.ww-image__tab');
		if (tab) {
			event.preventDefault();
			var wrap = tab.closest('.ww-image');
			wrap.querySelectorAll('.ww-image__tab').forEach(function (button) {
				button.setAttribute('aria-selected', button === tab ? 'true' : 'false');
			});
			wrap.querySelectorAll('.ww-image__panel').forEach(function (item) {
				item.hidden = item.getAttribute('data-panel') !== tab.getAttribute('data-tab');
			});
			return;
		}
		var choice = event.target.closest('.ww-contact__choice');
		if (choice) {
			event.preventDefault();
			var contact = choice.closest('.ww-contact');
			contact.querySelector('input[type="hidden"]').value = choice.getAttribute('data-id');
			contact.querySelector('.ww-contact__chosen').textContent = choice.textContent;
			contact.querySelector('.ww-contact__results').hidden = true;
			contact.querySelector('.ww-contact__search').value = '';
		}
	}

	function onChange(event) {
		if (event.target.name === 'ww_template') {
			var picker = document.querySelector('.ww-picker');
			var previous = picker ? picker.getAttribute('data-current') : '';
			activate(event.target.value, previous);
			return;
		}
		if (event.target.type === 'checkbox' && /^ww\[(locations|markets|services)\]\[\]$/.test(event.target.name)) {
			var source = event.target;
			document.querySelectorAll('input[type="checkbox"][name="' + source.name + '"]').forEach(function (box) {
				if (box.value === source.value) {
					box.checked = source.checked;
				}
			});
		}
		var mode = event.target.closest('.ww-contact select');
		if (mode) {
			mode.closest('.ww-contact').setAttribute('data-mode', mode.value);
		}
	}

	function onInput(event) {
		if (event.target.classList.contains('ww-contact__search')) {
			searchPeople(event.target);
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		var root = document.getElementById('ww-project-editor');
		if (!root) {
			return;
		}
		root.addEventListener('click', onClick);
		root.addEventListener('change', onChange);
		root.addEventListener('input', onInput);
		root.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' && event.target.classList.contains('ww-location-add__name')) {
				event.preventDefault();
				addLocation(event.target);
			}
		});
		root.addEventListener('toggle', function (event) {
			var details = event.target;
			if (!details.classList || !details.classList.contains('ww-block')) {
				return;
			}
			if (details.open) {
				scheduleEditors(details);
			} else {
				releaseEditors(details);
			}
		}, true);
		var selected = currentTemplate();
		if (selected) {
			activate(selected, '');
		}
		document.querySelectorAll('.ww-repeater__list').forEach(bindSortable);
	});

	document.addEventListener('ww-project-ui', function () {
		var selected = currentTemplate();
		if (selected) {
			scheduleEditors(panel(selected));
		}
	});
}(window.wp, window.jQuery));
