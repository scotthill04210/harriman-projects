(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.element || !wp.data) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var registerPlugin = wp.plugins.registerPlugin;
	var Panel = (wp.editPost && wp.editPost.PluginDocumentSettingPanel) || (wp.editor && wp.editor.PluginDocumentSettingPanel);
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var Modal = wp.components.Modal;
	var Notice = wp.components.Notice;

	if (!Panel || !Button || !Modal || !TextControl) {
		return;
	}

	function isOn(value) {
		return value === true || value === 1 || value === '1';
	}

	function syncFrames(flagged, hideCanvas) {
		var css = 'body.ww-hide-legacy-canvas .block-editor-block-list__layout > *,body.ww-hide-legacy-canvas .block-list-appender,body.ww-hide-legacy-canvas .block-editor-default-block-appender,body.ww-hide-legacy-canvas .editor-post-text-editor{display:none !important}body.ww-hide-legacy-canvas .editor-visual-editor__post-title-wrapper,body.ww-hide-legacy-canvas .editor-post-title,body.ww-hide-legacy-canvas .wp-block-post-title{display:block !important}';
		document.querySelectorAll('iframe').forEach(function (frame) {
			try {
				var doc = frame.contentDocument;
				if (!doc || !doc.body) {
					return;
				}
				if (!doc.getElementById('ww-canvas-hide')) {
					var style = doc.createElement('style');
					style.id = 'ww-canvas-hide';
					style.appendChild(doc.createTextNode(css));
					(doc.head || doc.documentElement).appendChild(style);
				}
				doc.body.classList.toggle('ww-flagged', flagged);
				doc.body.classList.toggle('ww-hide-legacy-canvas', hideCanvas);
			} catch (error) {
				return;
			}
		});
	}

	function SwitchPanel() {
		var modalState = useState(false);
		var modalOpen = modalState[0];
		var setModalOpen = modalState[1];
		var textState = useState('');
		var confirmText = textState[0];
		var setConfirmText = textState[1];
		var legacyState = useState(false);
		var showLegacy = legacyState[0];
		var setShowLegacy = legacyState[1];
		var errorState = useState('');
		var errorText = errorState[0];
		var setErrorText = errorState[1];
		var busyState = useState(false);
		var busy = busyState[0];
		var setBusy = busyState[1];

		var meta = useSelect(function (select) {
			var editor = select('core/editor');
			if (!editor || !editor.getCurrentPostType) {
				return { postType: '', postId: 0, flagged: false, deleted: false };
			}
			var values = editor.getEditedPostAttribute('meta') || {};
			var content = editor.getEditedPostAttribute('content') || '';
			var postId = editor.getCurrentPostId();
			var core = select('core');
			var record = postId ? core.getEntityRecord('postType', 'project', postId) : null;
			var resolving = postId && core.isResolving
				? core.isResolving('getEntityRecord', ['postType', 'project', postId])
				: false;
			var flag = Object.prototype.hasOwnProperty.call(values, '_ww_use_new_template')
				? isOn(values._ww_use_new_template)
				: true;
			return {
				postType: editor.getCurrentPostType(),
				postId: postId,
				flagged: flag,
				deleted: isOn(values._ww_legacy_deleted),
				hasLegacy: content.trim() !== '',
				archived: !!(record && record.ww_legacy_archived),
				archiveKnown: !postId || (!resolving && !!record)
			};
		}, []);

		var editPost = useDispatch('core/editor').editPost;

		useEffect(function () {
			var hideCanvas = meta.flagged && !showLegacy;
			document.body.classList.toggle('ww-flagged', meta.flagged);
			document.body.classList.toggle('ww-hide-legacy-canvas', hideCanvas);
			syncFrames(meta.flagged, hideCanvas);
			if (meta.postType === 'project' && meta.flagged && wp.data.dispatch) {
				var editPostStore = wp.data.dispatch('core/edit-post');
				if (editPostStore && editPostStore.removeEditorPanel) {
					['taxonomy-panel-market', 'taxonomy-panel-service', 'taxonomy-panel-project-location'].forEach(function (name) {
						editPostStore.removeEditorPanel(name);
					});
				}
			}
			document.dispatchEvent(new CustomEvent('ww-project-ui'));
		}, [meta.flagged, meta.postType, showLegacy]);

		if (meta.postType !== 'project') {
			return null;
		}

		function setFlag(on) {
			editPost({ meta: { _ww_use_new_template: on } });
		}

		function closeModal() {
			setModalOpen(false);
			setConfirmText('');
			setErrorText('');
		}

		function deleteLegacy() {
			setBusy(true);
			setErrorText('');
			wp.apiFetch({
				path: '/ww/v1/projects/' + meta.postId + '/delete-legacy',
				method: 'POST',
				data: { confirm: confirmText }
			}).then(function () {
				editPost({ meta: { _ww_legacy_deleted: true } });
				if (wp.data.dispatch('core/block-editor')) {
					wp.data.dispatch('core/block-editor').resetBlocks([]);
				}
				closeModal();
			}).catch(function (error) {
				var message = error && error.message ? error.message : 'The old layout could not be deleted.';
				setErrorText(message);
			}).finally(function () {
				setBusy(false);
			});
		}

		var children = [];

		if (!meta.flagged) {
			children.push(el(Button, {
				key: 'switch',
				variant: 'primary',
				onClick: function () { setFlag(true); }
			}, 'Switch to new template'));
		} else {
			if (meta.deleted) {
				children.push(el(Notice, {
					key: 'deleted',
					status: 'warning',
					isDismissible: false
				}, 'The old layout was deleted. Revert is no longer available.'));
			} else if (meta.hasLegacy && meta.archiveKnown) {
				children.push(el(Notice, {
					key: 'archived',
					status: 'info',
					isDismissible: false
				}, meta.archived
					? 'Legacy layout archived. Content is unchanged.'
					: 'Legacy layout hidden. It is archived when you first save.'));
			}
			if (meta.hasLegacy) {
				children.push(el(Button, {
					key: 'revert',
					variant: 'secondary',
					disabled: meta.deleted,
					onClick: function () { setFlag(false); }
				}, 'Revert to current template'));
				children.push(el(Button, {
					key: 'legacy',
					variant: 'secondary',
					onClick: function () { setShowLegacy(!showLegacy); }
				}, showLegacy ? 'Hide legacy content' : 'Show legacy content'));
				if (!meta.deleted) {
					var canDelete = meta.archiveKnown && meta.archived;
					children.push(el(Button, {
						key: 'delete',
						isDestructive: true,
						variant: 'secondary',
						disabled: !canDelete,
						onClick: function () {
							if (canDelete) {
								setModalOpen(true);
							}
						}
					}, 'Delete old format data'));
					if (meta.archiveKnown && !meta.archived) {
						children.push(el('p', {
							key: 'delete-help',
							className: 'ww-switch-help'
						}, 'Save once to archive the legacy layout first.'));
					}
				}
			}
		}

		if (modalOpen) {
			children.push(el(Modal, {
				key: 'modal',
				title: 'Delete old format data',
				onRequestClose: closeModal
			},
			el('p', null, 'This removes the archived layout and blanks the old project content for this post. Type DELETE to confirm.'),
			el(TextControl, {
				label: 'Type DELETE',
				value: confirmText,
				onChange: setConfirmText
			}),
			errorText ? el('p', { className: 'ww-switch-error' }, errorText) : null,
			el(Button, {
				variant: 'primary',
				isDestructive: true,
				disabled: confirmText !== 'DELETE' || busy,
				onClick: deleteLegacy
			}, busy ? 'Deleting…' : 'Delete')));
		}

		return el(Panel, {
			name: 'ww-project-switch',
			title: 'Project template',
			className: 'ww-switch-panel'
		}, children);
	}

	registerPlugin('ww-project-switch', {
		render: SwitchPanel,
		icon: 'layout'
	});
}(window.wp));
