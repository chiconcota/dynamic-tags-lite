(function (wp) {
	const { addFilter } = wp.hooks;
	const { InspectorControls, BlockControls } = wp.blockEditor;
	const { PanelBody, TextControl, SelectControl, ToolbarButton, Popover, Button, Spinner, BaseControl } = wp.components;
	const { createHigherOrderComponent } = wp.compose;
	const { useState, useEffect } = wp.element;

	console.log('DTL: Editor script loaded v1.3.1');

	// Ensure wp.apiFetch is available
	const apiFetch = wp.apiFetch || ((options) => {
		console.error('DTL: wp.apiFetch is missing');
		return Promise.reject('wp.apiFetch missing');
	});

	// 1. Add 'dynamicTag' attribute
	const addDynamicTagAttribute = (settings, name) => {
		if (!['core/paragraph', 'core/heading', 'core/image', 'core/video', 'core/button'].includes(name)) {
			return settings;
		}

		const attributes = settings.attributes || {};
		const newAttributes = {
			dynamicTag: {
				type: 'object',
				default: {
					enable: false,
					source: '',
					key: '',
					fallback: '',
					prefix: '',
					suffix: '',
					dateFormat: '',
					numberDecimals: '',
					showPreview: true
				},
			}
		};

		// Add dynamicLink specifically for images
		if (name === 'core/image') {
			newAttributes.dynamicLink = {
				type: 'object',
				default: {
					enable: false,
					source: '',
					key: '',
					fallback: '',
					prefix: '',
					suffix: '',
					dateFormat: '',
					numberDecimals: '',
					showPreview: true
				},
			};
		}

		return Object.assign({}, settings, {
			attributes: Object.assign({}, attributes, newAttributes),
		});
	};

	addFilter('blocks.registerBlockType', 'dynamic-tags-lite/add-attribute', addDynamicTagAttribute);

	// 2. Toolbar Component
	const withDynamicTagToolbar = createHigherOrderComponent((BlockEdit) => {
		return (props) => {
			const { name, attributes, setAttributes, isSelected } = props;

			if (!['core/paragraph', 'core/heading', 'core/image', 'core/video', 'core/button'].includes(name)) {
				return wp.element.createElement(BlockEdit, props);
			}

			// Settings
			const dynamicTag = attributes.dynamicTag || { enable: false, source: '', key: '', fallback: '', prefix: '', suffix: '', dateFormat: '', numberDecimals: '', showPreview: true };
			const dynamicLink = attributes.dynamicLink || { enable: false, source: '', key: '', fallback: '', prefix: '', suffix: '', dateFormat: '', numberDecimals: '', showPreview: true };

			// UI State
			const [isPopoverOpen, setIsPopoverOpen] = useState(false);
			const [activeTab, setActiveTab] = useState('content'); // 'content' or 'link'
			const [metaOptions, setMetaOptions] = useState([]);
			const [scfOptions, setSCFOptions] = useState([]);
			const [isLoadingKeys, setIsLoadingKeys] = useState(false);
			const [isLoadingSCF, setIsLoadingSCF] = useState(false);
			const [fetchError, setFetchError] = useState(null);
			const [keysLoaded, setKeysLoaded] = useState(false);
			const [scfLoaded, setSCFLoaded] = useState(false);
			const [showAdvanced, setShowAdvanced] = useState(false);

			const togglePopover = () => setIsPopoverOpen(!isPopoverOpen);

			// Determine current Context
			const isLinkMode = (name === 'core/image' && activeTab === 'link');
			const currentSettings = isLinkMode ? dynamicLink : dynamicTag;
			const hasActiveTag = dynamicTag.enable || (name === 'core/image' && dynamicLink.enable);

			// Function to Fetch Keys
			const fetchMetaKeys = () => {
				setIsLoadingKeys(true);
				setFetchError(null);

				apiFetch({ path: '/dynamic-tags-lite/v1/meta-keys' })
					.then((keys) => {
						setMetaOptions(keys);
						setKeysLoaded(true);
						setIsLoadingKeys(false);
					})
					.catch((err) => {
						console.error('DTL: Fetch error', err);
						setFetchError(err.message || 'Check Console');
						setIsLoadingKeys(false);
					});
			};

			const fetchSCFFields = () => {
				setIsLoadingSCF(true);
				setFetchError(null);

				apiFetch({ path: '/dynamic-tags-lite/v1/scf-fields' })
					.then((options) => {
						setSCFOptions(options);
						setSCFLoaded(true);
						setIsLoadingSCF(false);
					})
					.catch((err) => {
						console.error('DTL: SCF Fetch error', err);
						setFetchError(err.message || 'SCF not found');
						setIsLoadingSCF(false);
					});
			};

			// Auto-fetch on first open if empty
			useEffect(() => {
				if (isPopoverOpen && !isLoadingKeys) {
					if (currentSettings.source === 'post-meta' && !keysLoaded) {
						fetchMetaKeys();
					} else if (currentSettings.source === 'scf' && !scfLoaded) {
						fetchSCFFields();
					}
				}
			}, [isPopoverOpen, currentSettings.source]);

			// Effect: Synchronize Dynamic Values to Block Attributes (Preview in Editor)
			useEffect(() => {
				if (!hasActiveTag) return;

				const postId = wp.data.select('core/editor').getCurrentPostId();

				// 1. Sync Image Source
				if (name === 'core/image' && dynamicTag.enable && dynamicTag.source && dynamicTag.key) {
					apiFetch({ path: `/dynamic-tags-lite/v1/get-value?source=${dynamicTag.source}&key=${dynamicTag.key}&post_id=${postId}` })
						.then((res) => {
							if (res.value && res.value !== attributes.url) {
								setAttributes({
									url: res.value,
									id: 0,
									sizeSlug: 'full'
								});
							}
						});
				}

				// 2. Sync Image Link
				if (name === 'core/image' && dynamicLink.enable && dynamicLink.source && dynamicLink.key) {
					apiFetch({ path: `/dynamic-tags-lite/v1/get-value?source=${dynamicLink.source}&key=${dynamicLink.key}&post_id=${postId || 0}` })
						.then((res) => {
							if (res.value && res.value !== attributes.href) {
								setAttributes({
									href: res.value,
									linkDestination: 'custom'
								});
							}
						});
				}

				// 3. Sync Button Link
				if (name === 'core/button' && dynamicTag.enable && dynamicTag.source && dynamicTag.key) {
					apiFetch({ path: `/dynamic-tags-lite/v1/get-value?source=${dynamicTag.source}&key=${dynamicTag.key}&post_id=${postId || 0}` })
						.then((res) => {
							if (res.value && res.value !== attributes.url) {
								setAttributes({ url: res.value });
							}
						});
				}

				// 4. Sync Text Content (Paragraph/Heading) with Debounce
				if (['core/paragraph', 'core/heading'].includes(name) && dynamicTag.enable && dynamicTag.source && dynamicTag.key) {
					if (!dynamicTag.showPreview) {
						const placeholder = `%% ${dynamicTag.key} %%`;
						if (attributes.content !== placeholder) {
							setAttributes({ content: placeholder });
						}
						return;
					}

					const timeoutId = setTimeout(() => {
						const queryParams = new URLSearchParams({
							source: dynamicTag.source,
							key: dynamicTag.key,
							post_id: postId || 0,
							prefix: dynamicTag.prefix || '',
							suffix: dynamicTag.suffix || '',
							dateFormat: dynamicTag.dateFormat || '',
							numberDecimals: dynamicTag.numberDecimals || ''
						}).toString();

						apiFetch({ path: `/dynamic-tags-lite/v1/get-value?${queryParams}` })
							.then((res) => {
								if (res.value !== undefined && res.value !== attributes.content) {
									setAttributes({ content: res.value });
								}
							});
					}, 600);

					return () => clearTimeout(timeoutId);
				}
			}, [
				dynamicTag.enable,
				dynamicTag.source,
				dynamicTag.key,
				dynamicTag.showPreview,
				dynamicTag.prefix,
				dynamicTag.suffix,
				dynamicTag.dateFormat,
				dynamicTag.numberDecimals,
				dynamicLink.enable,
				dynamicLink.source,
				dynamicLink.key
			]);

			const updateDynamicTag = (key, value) => {
				const newSettings = {
					...currentSettings,
					[key]: value,
					enable: true,
				};

				const newAttributes = {};
				if (isLinkMode) {
					newAttributes.dynamicLink = newSettings;
				} else {
					newAttributes.dynamicTag = newSettings;

					if (key === 'key' && ['core/paragraph', 'core/heading'].includes(name)) {
						if (value && value !== 'custom') {
							newAttributes.content = `%% ${value} %%`;
						}
					}
				}
				setAttributes(newAttributes);
			};

			const removeDynamicTag = () => {
				const empty = { enable: false, source: '', key: '', fallback: '', prefix: '', suffix: '', dateFormat: '', numberDecimals: '', showPreview: true };
				if (isLinkMode) {
					setAttributes({ dynamicLink: empty, href: '' });
				} else {
					setAttributes({ dynamicTag: empty });
					if (name === 'core/image') setAttributes({ url: '' });
				}
				setIsPopoverOpen(false);
			}

			const hasKey = (key) => metaOptions.find(o => o.value === key);
			const dropdownValue = hasKey(currentSettings.key) ? currentSettings.key : 'custom';

			const isMediaBlock = ['core/image', 'core/video'].includes(name);

			const postDataOptionsText = [
				{ label: 'Select Field...', value: '' },
				{ label: 'ID', value: 'ID' },
				{ label: 'Title', value: 'post_title' },
				{ label: 'Permalink', value: 'post_url' },
				{ label: 'Date', value: 'post_date' },
				{ label: 'Date Modified', value: 'post_modified' },
				{ label: 'Excerpt', value: 'post_excerpt' },
				{ label: 'Content', value: 'post_content' },
				{ label: 'Status', value: 'post_status' },
				{ label: 'Slug (Name)', value: 'post_name' },
				{ label: 'Parent ID', value: 'post_parent' },
				{ label: 'Post Type', value: 'post_type' },
				{ label: 'MIME Type', value: 'post_mime_type' },
				{ label: 'Comment Count', value: 'comment_count' },
				{ label: 'Author Name', value: 'post_author_name' },
				{ label: 'Author ID', value: 'post_author' },
				{ label: 'Categories', value: 'post_categories' },
				{ label: 'Tags', value: 'post_tags' },
				{ label: 'Author URL', value: 'post_author_url' },
				{ label: 'Home URL', value: 'home_url' },
			];

			const postDataOptionsMedia = [
				{ label: 'Select Field...', value: '' },
				{ label: 'Featured Image', value: 'post_thumbnail_url' },
				{ label: 'Author Profile Picture', value: 'post_author_avatar_url' },
				{ label: 'Site Logo', value: 'site_logo_url' },
				{ label: 'Custom Meta Image', value: 'custom_meta_image' },
			];

			const currentPostDataOptions = (!isMediaBlock || isLinkMode) ? postDataOptionsText : postDataOptionsMedia;

			const safeKey = String(currentSettings.key || '');
			const isDateField = safeKey.includes('date') || safeKey.includes('modified');
			const isNumberField = safeKey.includes('price') || safeKey.includes('count') || safeKey.includes('ID');

			return [
				wp.element.createElement(BlockEdit, { ...props, key: 'block-edit' }),
				isSelected && wp.element.createElement(
					BlockControls,
					{ key: 'block-controls' },
					wp.element.createElement(ToolbarButton, {
						icon: hasActiveTag ? 'yes' : 'database',
						label: 'Dynamic Tag',
						onClick: togglePopover,
						isActive: isPopoverOpen,
					}),
					hasActiveTag && wp.element.createElement(ToolbarButton, {
						icon: dynamicTag.showPreview ? 'visibility' : 'hidden',
						label: dynamicTag.showPreview ? 'Show Original' : 'Show Preview',
						onClick: () => updateDynamicTag('showPreview', !dynamicTag.showPreview),
						isActive: dynamicTag.showPreview,
					})
				),
				isPopoverOpen && wp.element.createElement(
					Popover,
					{
						onClose: () => setIsPopoverOpen(false),
						position: 'bottom center',
						key: 'dtl-popover'
					},
					wp.element.createElement(
						'div',
						{ style: { padding: '16px', minWidth: '300px' } },

						(name === 'core/image') && wp.element.createElement('div', { style: { display: 'flex', borderBottom: '1px solid #eee', marginBottom: '15px', paddingBottom: '10px' } },
							wp.element.createElement(Button, {
								isSmall: true,
								variant: activeTab === 'content' ? 'primary' : 'tertiary',
								onClick: () => setActiveTab('content'),
								style: { marginRight: '10px' }
							}, 'Image Source'),
							wp.element.createElement(Button, {
								isSmall: true,
								variant: activeTab === 'link' ? 'primary' : 'tertiary',
								onClick: () => setActiveTab('link')
							}, 'Image Link')
						),

						wp.element.createElement('div', { style: { fontWeight: '600', marginBottom: '12px' } },
							isLinkMode ? 'Dynamic Link Settings' : 'Dynamic Content Settings'
						),

						wp.element.createElement(SelectControl, {
							label: 'Source',
							value: currentSettings.source || '',
							options: [
								{ label: 'Select Source...', value: '' },
								{ label: 'Post Meta', value: 'post-meta' },
								{ label: 'Post Data', value: 'post-data' },
								{ label: 'Secure Custom Fields', value: 'scf' },
							],
							onChange: (val) => updateDynamicTag('source', val),
						}),

						(currentSettings.source === 'post-meta') && wp.element.createElement('div', { style: { marginBottom: '16px', borderLeft: '2px solid #ddd', paddingLeft: '10px' } },
							wp.element.createElement('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' } },
								wp.element.createElement('label', { style: { fontSize: '11px', fontWeight: '500', textTransform: 'uppercase' } }, 'Meta Key'),
								wp.element.createElement(Button, {
									isSmall: true,
									variant: 'secondary',
									onClick: fetchMetaKeys,
									disabled: isLoadingKeys
								}, isLoadingKeys ? 'Loading...' : 'Refresh Keys')
							),

							fetchError && wp.element.createElement('div', { style: { color: '#cc1818', fontSize: '12px', marginBottom: '8px' } }, 'Error: ' + fetchError),

							wp.element.createElement(SelectControl, {
								value: dropdownValue,
								options: [
									{ label: 'Select Field...', value: '' },
									...metaOptions,
									{ label: 'Custom Key...', value: 'custom' }
								],
								onChange: (val) => {
									if (val !== 'custom') updateDynamicTag('key', val);
								},
							}),

							(dropdownValue === 'custom') && wp.element.createElement(TextControl, {
								placeholder: 'Enter custom meta key...',
								value: currentSettings.key || '',
								onChange: (val) => updateDynamicTag('key', val),
								help: 'Type the exact meta key name from the database.'
							})
						),

						// SCF Section
						(currentSettings.source === 'scf') && wp.element.createElement('div', { style: { marginBottom: '16px', borderLeft: '2px solid #2271b1', paddingLeft: '10px' } },
							wp.element.createElement('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' } },
								wp.element.createElement('label', { style: { fontSize: '11px', fontWeight: '500', textTransform: 'uppercase' } }, 'SCF Field'),
								wp.element.createElement(Button, {
									isSmall: true,
									variant: 'secondary',
									onClick: fetchSCFFields,
									disabled: isLoadingSCF
								}, isLoadingSCF ? 'Loading...' : 'Refresh')
							),

							wp.element.createElement(SelectControl, {
								value: currentSettings.key,
								options: [
									{ label: 'Select SCF Field...', value: '' },
									...scfOptions
								],
								onChange: (val) => updateDynamicTag('key', val),
							})
						),

						(currentSettings.source === 'post-data') && wp.element.createElement(SelectControl, {
							label: 'Field',
							value: currentSettings.key,
							options: currentPostDataOptions,
							onChange: (val) => updateDynamicTag('key', val),
						}),

						wp.element.createElement(TextControl, {
							label: 'Fallback Text',
							value: currentSettings.fallback || '',
							onChange: (val) => updateDynamicTag('fallback', val),
						}),

						wp.element.createElement('div', { style: { marginTop: '10px', borderTop: '1px solid #eee', paddingTop: '10px' } },
							wp.element.createElement(Button, {
								isLink: true,
								onClick: () => setShowAdvanced(!showAdvanced),
								icon: showAdvanced ? 'arrow-up-alt2' : 'arrow-down-alt2',
								style: { width: '100%', justifyContent: 'space-between', padding: '0 5px' }
							}, 'Advanced Settings')
						),

						showAdvanced && wp.element.createElement('div', { style: { marginTop: '10px', padding: '10px', background: '#f9f9f9', borderRadius: '4px' } },
							wp.element.createElement('div', { style: { display: 'flex', gap: '8px' } },
								wp.element.createElement('div', { style: { flex: 1 } },
									wp.element.createElement(TextControl, {
										label: 'Prefix',
										value: currentSettings.prefix || '',
										onChange: (val) => updateDynamicTag('prefix', val),
									})
								),
								wp.element.createElement('div', { style: { flex: 1 } },
									wp.element.createElement(TextControl, {
										label: 'Suffix',
										value: currentSettings.suffix || '',
										onChange: (val) => updateDynamicTag('suffix', val),
									})
								)
							),

							isDateField && wp.element.createElement(SelectControl, {
								label: 'Date Format',
								value: currentSettings.dateFormat || '',
								options: [
									{ label: 'Default', value: '' },
									{ label: 'F j, Y (July 30, 2025)', value: 'F j, Y' },
									{ label: 'Y-m-d (2025-07-30)', value: 'Y-m-d' },
									{ label: 'd/m/Y (30/07/2025)', value: 'd/m/Y' },
									{ label: 'm/d/Y (07/30/2025)', value: 'm/d/Y' },
								],
								onChange: (val) => updateDynamicTag('dateFormat', val),
							}),

							isNumberField && wp.element.createElement(TextControl, {
								label: 'Decimals',
								type: 'number',
								min: 0,
								max: 5,
								value: currentSettings.numberDecimals || '',
								onChange: (val) => updateDynamicTag('numberDecimals', val),
							})
						),

						currentSettings.enable && wp.element.createElement(
							Button,
							{
								isDestructive: true,
								variant: 'link',
								onClick: removeDynamicTag,
								style: { width: '100%', textAlign: 'center' }
							},
							'Remove Dynamic Tag'
						)
					)
				)
			];
		};
	}, 'withDynamicTagToolbar');

	addFilter('editor.BlockEdit', 'dynamic-tags-lite/with-toolbar', withDynamicTagToolbar);

	// ==========================================
	// 3. Dynamic Link Format (Inline)
	// ==========================================
	const { registerFormatType, toggleFormat, applyFormat, removeFormat } = wp.richText;
	const { RichTextToolbarButton } = wp.blockEditor;

	const DynamicLinkEdit = ({ isActive, value, onChange, contentRef }) => {
		const [isPopoverOpen, setIsPopoverOpen] = useState(false);
		const [attributes, setAttributes] = useState(() => {
			const activeFormat = value.activeFormats?.find(f => f.type === 'dynamic-tags-lite/dynamic-link');
			return activeFormat?.attributes || { source: '', key: '', href: '#' };
		});

		// Internal state for Meta Keys
		const [metaOptions, setMetaOptions] = useState([]);
		const [scfOptions, setSCFOptions] = useState([]);
		const [isLoadingKeys, setIsLoadingKeys] = useState(false);
		const [isLoadingSCF, setIsLoadingSCF] = useState(false);
		const [keysLoaded, setKeysLoaded] = useState(false);
		const [scfLoaded, setSCFLoaded] = useState(false);

		const togglePopover = () => {
			setIsPopoverOpen(!isPopoverOpen);
			if (!isPopoverOpen && isActive) {
				// Load existing attributes if active
				const format = value.activeFormats?.find(f => f.type === 'dynamic-tags-lite/dynamic-link');
				if (format) {
					setAttributes(format.attributes);
				}
			}
		};

		const fetchMetaKeys = () => {
			setIsLoadingKeys(true);
			apiFetch({ path: '/dynamic-tags-lite/v1/meta-keys' })
				.then((keys) => {
					setMetaOptions(keys);
					setKeysLoaded(true);
					setIsLoadingKeys(false);
				})
				.catch((err) => {
					console.error('DTL Link: Fetch error', err);
					setIsLoadingKeys(false);
				});
		};

		const fetchSCFFields = () => {
			setIsLoadingSCF(true);
			apiFetch({ path: '/dynamic-tags-lite/v1/scf-fields' })
				.then((options) => {
					setSCFOptions(options);
					setSCFLoaded(true);
					setIsLoadingSCF(false);
				})
				.catch((err) => {
					console.error('DTL Link: SCF Fetch error', err);
					setIsLoadingSCF(false);
				});
		};

		useEffect(() => {
			if (isPopoverOpen && !isLoadingKeys && !isLoadingSCF) {
				if (attributes.source === 'post-meta' && !keysLoaded) {
					fetchMetaKeys();
				} else if (attributes.source === 'scf' && !scfLoaded) {
					fetchSCFFields();
				}
			}
		}, [isPopoverOpen, attributes.source]);

		const applyDynamicLink = () => {
			onChange(applyFormat(value, {
				type: 'dynamic-tags-lite/dynamic-link',
				attributes: {
					source: attributes.source,
					key: attributes.key,
					href: '#', // Required for <a> tag
					style: 'text-decoration: underline; color: var( --wp--preset--color--primary, #0073aa );' // Visual indicator
				},
			}));
			setIsPopoverOpen(false);
		};

		const removeDynamicLink = () => {
			onChange(removeFormat(value, 'dynamic-tags-lite/dynamic-link'));
			setIsPopoverOpen(false);
			setAttributes({ source: '', key: '', href: '#' });
		};

		const postDataOptions = [
			{ label: 'Select Field...', value: '' },
			{ label: 'Title', value: 'post_title' },
			{ label: 'Permalink', value: 'post_url' }, // Added useful link field
			{ label: 'Author URL', value: 'post_author_url' }, // Added useful link field
			{ label: 'Home URL', value: 'home_url' },
			{ label: 'ID', value: 'ID' },
			{ label: 'Slug', value: 'post_name' },
		];

		const linkIcon = wp.element.createElement('svg', { width: 20, height: 20, viewBox: "0 0 24 24" },
			wp.element.createElement('path', { d: "M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z", fill: "currentColor" }),
			wp.element.createElement('path', { d: "M0 0h24v24H0z", fill: "none" })
		);

		return wp.element.createElement(wp.element.Fragment, {},
			wp.element.createElement(RichTextToolbarButton, {
				icon: linkIcon,
				title: 'Dynamic Link',
				onClick: togglePopover,
				isActive: isActive,
			}),
			isPopoverOpen && wp.element.createElement(Popover, {
				position: 'bottom center',
				onClose: () => setIsPopoverOpen(false),
				key: 'dtl-link-popover'
			},
				wp.element.createElement('div', { style: { padding: '16px', minWidth: '280px' } },
					wp.element.createElement(SelectControl, {
						label: 'Source',
						value: attributes.source,
						options: [
							{ label: 'Select Source...', value: '' },
							{ label: 'Post Meta', value: 'post-meta' },
							{ label: 'Post Data', value: 'post-data' },
							{ label: 'Secure Custom Fields', value: 'scf' },
						],
						onChange: (val) => setAttributes({ ...attributes, source: val })
					}),

					(attributes.source === 'post-meta') && wp.element.createElement(SelectControl, {
						label: 'Meta Key',
						value: attributes.key,
						options: [
							{ label: 'Select Field...', value: '' },
							...metaOptions,
							{ label: 'Custom...', value: 'custom' }
						],
						onChange: (val) => setAttributes({ ...attributes, key: val })
					}),
					(attributes.source === 'post-meta' && attributes.key === 'custom') && wp.element.createElement(TextControl, {
						placeholder: 'Enter meta key...',
						value: attributes.customKey || '', // Store temp
						onChange: (val) => setAttributes({ ...attributes, customKey: val, key: val })
					}),

					(attributes.source === 'post-data') && wp.element.createElement(SelectControl, {
						label: 'Data Field',
						value: attributes.key,
						options: postDataOptions,
						onChange: (val) => setAttributes({ ...attributes, key: val })
					}),

					(attributes.source === 'scf') && wp.element.createElement(SelectControl, {
						label: 'SCF Field',
						value: attributes.key,
						options: [
							{ label: 'Select SCF Field...', value: '' },
							...scfOptions
						],
						onChange: (val) => setAttributes({ ...attributes, key: val })
					}),

					wp.element.createElement('div', { style: { display: 'flex', gap: '8px', marginTop: '16px' } },
						wp.element.createElement(Button, {
							isPrimary: true,
							onClick: applyDynamicLink
						}, 'Apply'),
						isActive && wp.element.createElement(Button, {
							isDestructive: true,
							variant: 'link',
							onClick: removeDynamicLink
						}, 'Remove')
					)
				)
			)
		);
	};

	registerFormatType('dynamic-tags-lite/dynamic-link', {
		title: 'Dynamic Link',
		tagName: 'a',
		className: 'dtl-dynamic-link',
		attributes: {
			source: 'data-dtl-source',
			key: 'data-dtl-key',
			href: 'href',
		},
		edit: DynamicLinkEdit,
	});

})(window.wp);
