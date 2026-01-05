import { useState, useEffect } from "@wordpress/element";
import { TextControl, PanelBody, PanelRow } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { BlockControls, AlignmentToolbar, InspectorControls } from "@wordpress/block-editor";
import { useBlockProps } from "@wordpress/block-editor";
import "./editor.scss";

function generateRandomHash() {
	return (
		Math.random().toString(36).substr(2, 9) +
		Date.now().toString(36).substr(2, 5)
	);
}

export default function Edit({ attributes, setAttributes }) {
	const { fontSize, lineHeight, align, backgroundColor, textColor, className } =
		attributes;
	const blockId = generateRandomHash();

	const [fonts, setFonts] = useState({});
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [searchTerm, setSearchTerm] = useState("");

	const [selectedIcon, setSelectedIcon] = useState({ className });

	useEffect(() => {
		const fetchData = async () => {
			try {
				let response = await fetch("/wp-json/easysymbolsicons/v1/loaded-fonts");

				if (!response.ok) {
					response = await fetch("/?rest_route=/easysymbolsicons/v1/loaded-fonts");
				}

				if (!response.ok) {
					throw new Error(`HTTP error ${response.status}`);
				}

				const text = await response.text();
				let json;

				try {
					json = JSON.parse(text);
				} catch {
					json = [];
				}

				if (Array.isArray(json) && json.length === 0) {
					setError(
						<span>
							No fonts found. Please visit the{' '}
							<a href="/wp-admin/admin.php?page=eics_settings-page&tab=fontselect">
								font selection page
							</a>{' '}
							to add fonts.
						</span>
					);
				} else if (json && typeof json === "object") {
					setFonts(json);
				} else {
					setError("Data is not in the expected format.");
				}

				setLoading(false);
			} catch (error) {
				setError("Failed to fetch fonts");
				setLoading(false);
				console.error(error);
			}
		};

		fetchData();
	}, []);

	const filteredFonts = Object.keys(fonts)
		.map((fontFolder) => {
			const fontArray = fonts[fontFolder];

			const fontEntries = Object.entries(fontArray);

			const filteredGlyphs = fontEntries.filter(([name]) => {
				return name.toLowerCase().includes(searchTerm.toLowerCase());
			});

			return {
				fontFolder,
				glyphs: filteredGlyphs,
			};
		})
		.filter((font) => font.glyphs.length > 0);

	const handleTypographyChange = (value, property) => {
		setAttributes({ [property]: value });
	};

	const handleAlignmentChange = (newAlign) => {
		setAttributes({ align: newAlign });
	};

	const handleIconClick = (className) => {
		const matches = className.match(/^eics-([^\s]+)__([^\s]+)$/i);

		if (!matches) {
			console.warn("Invalid icon class:", className);
			return;
		}

		const fontName = matches[1];
		const iconName = matches[2];

		setAttributes({
			className,
			font: fontName,
			icon: iconName,
		});

		setSelectedIcon({ className, font: fontName, icon: iconName });
	};

	const blockProps = useBlockProps({
		style: {
			fontSize: fontSize ? `${fontSize}px` : undefined,
			lineHeight: lineHeight ? `${lineHeight}px` : undefined,
			backgroundColor: backgroundColor || undefined,
			color: textColor || undefined,
		},
	});

	const wrapperClass = `selected-icon-wrapper align${align}`;
	const selectorID = `eics-icon-grid-${blockId}`;

	const isIconValid = (iconClassName, loadedFonts) => {
		for (const fontFolder in loadedFonts) {
			const fontGlyphs = loadedFonts[fontFolder];
			for (const glyphName in fontGlyphs) {
				const expectedClass = `eics-${fontFolder.toLowerCase()}__${glyphName.toLowerCase()}`;
				if (iconClassName === expectedClass) {
					return true;
				}
			}
		}
		return false;
	};

	return (
		<>
			<BlockControls>
				<AlignmentToolbar value={align} onChange={handleAlignmentChange} />
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__("Selected Icon", "easy-symbols-icons")} initialOpen={true}>
					<PanelRow>
						{selectedIcon.className ? (
							<>
								<div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
									<span
										className={selectedIcon.className}
										style={{ fontSize: "24px" }}
									></span>

									<div>
										<p>
											<strong>{__("Icon:", "easy-symbols-icons")}</strong>{" "}
											{selectedIcon.icon || "-"}
										</p>
										<p>
											<strong>{__("Font:", "easy-symbols-icons")}</strong>{" "}
											{selectedIcon.font || "-"}
										</p>
										<p>
											<strong>{__("Class:", "easy-symbols-icons")}</strong>{" "}
											{selectedIcon.className}
										</p>
									</div>
								</div>
							</>
						) : (
							<p>{__("No icon selected", "easy-symbols-icons")}</p>
						)}
					</PanelRow>
				</PanelBody>
			</InspectorControls>

			<div
				{...blockProps}
				className={`${blockProps.className} ${wrapperClass}`}
			>
				{selectedIcon.className &&
				isIconValid(selectedIcon.className, fonts) ? (
					<button
						className={selectedIcon.className + " eics-select-button-has-icon"}
						style={{ cursor: "pointer" }}
						popovertarget={selectorID}
					></button>
				) : (
					<button className="eics-select-button" popovertarget={selectorID}>
						{__("add icon", "easy-symbols-icons")}
					</button>
				)}
			</div>

			{
				<div className="eics-icon-grid" id={selectorID} popover="auto">
					<div className="eics-icon-search">
						<TextControl
							value={searchTerm}
							onChange={(value) => setSearchTerm(value)}
							placeholder={__(
								"search icon by glyph name...",
								"easy-symbols-icons",
							)}
						/>
					</div>

					<div className="eics-icon-font-selects">
						{loading && <p>{__("Loading fonts...", "easy-symbols-icons")}</p>}
						{error && (
							<p>
								{__("Error: ", "easy-symbols-icons")}
								{error}
							</p>
						)}
						{!loading &&
							!error &&
							filteredFonts.length > 0 &&
							filteredFonts.map((font, index) => (
								<details key={index} className="eics-font-details" open>
									<summary>{font.fontFolder}</summary>
									<div className="eics-font-icons">
										{font.glyphs.map(([name], i) => {
											const iconClass = `eics-${font.fontFolder.toLowerCase()}__${name.toLowerCase()}`;

											return (
												<span
													key={i}
													className="eics-font-icon"
													onClick={() => handleIconClick(iconClass)} // Select icon on click
													style={{
														cursor: "pointer",
														fontSize: "20px",
														margin: "5px",
													}}
												>
													<span className={iconClass}></span>
												</span>
											);
										})}
									</div>
								</details>
							))}
					</div>

					{!loading && !error && filteredFonts.length === 0 && (
						<p>{__("No fonts found", "easy-symbols-icons")}</p>
					)}
				</div>
			}
		</>
	);
}
