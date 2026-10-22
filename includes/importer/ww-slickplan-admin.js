(function () {
	'use strict';

	var button = document.getElementById('ww-slickplan-apply');
	if (!button || !window.wwSlickplan) {
		return;
	}

	button.addEventListener('click', function () {
		var rows = Array.prototype.slice.call(document.querySelectorAll('.ww-slickplan-row'));
		var selected = [];
		rows.forEach(function (row) {
			var box = row.querySelector('.ww-slickplan-check');
			if (!box || !box.checked || box.disabled) {
				return;
			}
			var pick = row.querySelector('select');
			var title = row.querySelector('.ww-slickplan-title');
			selected.push({
				cell_id: row.getAttribute('data-cell'),
				post_id: pick ? pick.value : row.getAttribute('data-post'),
				update_title: title && title.checked ? '1' : '0',
				tries: 0
			});
		});
		if (!selected.length) {
			return;
		}
		button.disabled = true;
		var total = selected.length;
		var finished = 0;
		var reset = true;
		var bar = document.getElementById('ww-slickplan-bar');
		var status = document.getElementById('ww-slickplan-status');

		function paint(extra) {
			bar.style.width = Math.round((finished / total) * 100) + '%';
			status.textContent = finished + ' of ' + total + (extra ? ' ' + extra : '');
		}

		function mark(cellId, text) {
			var row = document.querySelector('.ww-slickplan-row[data-cell="' + cellId + '"]');
			var cell = row ? row.querySelector('.ww-slickplan-state') : null;
			if (cell) {
				cell.textContent = text;
			}
		}

		function send(chunk, attempt) {
			var body = new FormData();
			body.append('action', 'ww_slickplan_batch');
			body.append('_ajax_nonce', wwSlickplan.nonce);
			body.append('reset_fails', reset && attempt === 0 ? '1' : '0');
			reset = false;
			chunk.forEach(function (item, index) {
				body.append('items[' + index + '][cell_id]', item.cell_id);
				body.append('items[' + index + '][post_id]', item.post_id);
				body.append('items[' + index + '][update_title]', item.update_title);
			});
			window.fetch(window.ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (response) { return response.json(); })
				.then(function (payload) {
					if (!payload || !payload.success) {
						if (attempt < 2) {
							send(chunk, attempt + 1);
							return;
						}
						status.textContent = payload && payload.data && payload.data.message ? payload.data.message : 'The import request failed.';
						button.disabled = false;
						return;
					}
					var again = [];
					var byCell = {};
					chunk.forEach(function (item) { byCell[item.cell_id] = item; });
					var results = payload.data && payload.data.results ? payload.data.results : [];
					results.forEach(function (result) {
						var source = byCell[result.cell_id] || { update_title: '0', tries: 0, post_id: 0 };
						var notes = result.images && result.images.length ? ' ' + result.images.join('; ') : '';
						if (result.status === 'downloading' || result.status === 'deferred') {
							var tries = source.tries + (result.status === 'downloading' ? 1 : 0);
							if (tries > 80) {
								mark(result.cell_id, 'Image downloads stopped after repeated retries.' + notes);
								finished += 1;
								return;
							}
							mark(result.cell_id, (result.status === 'downloading' ? 'Downloading images…' : 'Waiting to download images…') + notes);
							again.push({
								cell_id: result.cell_id,
								post_id: result.post_id || source.post_id,
								update_title: source.update_title,
								tries: tries
							});
							return;
						}
						if (result.error) {
							mark(result.cell_id, result.error);
						} else {
							mark(result.cell_id, result.status + (result.post_id ? ' #' + result.post_id : '') + notes);
						}
						finished += 1;
					});
					selected = again.concat(selected);
					paint(again.length ? '(downloading images)' : '');
					next();
				})
				.catch(function () {
					if (attempt < 2) {
						send(chunk, attempt + 1);
						return;
					}
					chunk.forEach(function (item) {
						mark(item.cell_id, 'Request failed. Run Import selected again to resume.');
					});
					finished += chunk.length;
					paint();
					next();
				});
		}

		function next() {
			var chunk = selected.splice(0, 5);
			if (!chunk.length) {
				button.disabled = false;
				status.textContent = 'Finished ' + finished + ' of ' + total + '.';
				return;
			}
			send(chunk, 0);
		}

		paint();
		next();
	});
}());
