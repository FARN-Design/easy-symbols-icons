import { useState, useEffect } from "@wordpress/element";
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { BlockControls, AlignmentToolbar } from "@wordpress/block-editor";
import { useBlockProps } from "@wordpress/block-editor";
import './editor.scss';

function generateRandomHash() {
    return Math.random().toString(36).substr(2, 9) + Date.now().toString(36).substr(2, 5);
}

export default function Edit({ attributes, setAttributes }) {
    const { fontSize, lineHeight, align, backgroundColor, textColor, className } = attributes;
    const blockId = generateRandomHash();

    const [fonts, setFonts] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');

    const [selectedIcon, setSelectedIcon] = useState({ className });


    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await fetch('/wp-json/easyiconfonts/v1/loaded-fonts');

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const json = await response.json();

                if (typeof json === 'object') {
                    setFonts(json);
                } else {
                    setError('Data is not in the expected format.');
                }

                setLoading(false);
            } catch (error) {
                setError('Failed to fetch fonts');
                setLoading(false);
                console.error(error);
            }
        };

        fetchData();
    }, []);

    const filteredFonts = Object.keys(fonts).map(fontFolder => {
        const fontArray = fonts[fontFolder];

        const fontEntries = Object.entries(fontArray);

        const filteredGlyphs = fontEntries.filter(([name]) => {
            return name.toLowerCase().includes(searchTerm.toLowerCase());
        });

        return {
            fontFolder,
            glyphs: filteredGlyphs
        };
    }).filter(font => font.glyphs.length > 0);

    const handleTypographyChange = (value, property) => {
        setAttributes({ [property]: value });
    };

    const handleAlignmentChange = (newAlign) => {
        setAttributes({ align: newAlign });
    };

    const handleIconClick = (className) => {
        setSelectedIcon({ className });
        setAttributes({ className });
    };

    const blockProps = useBlockProps({
        style: {
            fontSize: fontSize ? `${fontSize}px` : undefined,
            lineHeight: lineHeight ? `${lineHeight}px` : undefined,
            backgroundColor: backgroundColor || undefined,
            color: textColor || undefined,
        }
    });

    const wrapperClass = `selected-icon-wrapper align${align}`;
    const selectorID = `esi-icon-grid-${blockId}`;

    const isIconValid = (iconClassName, loadedFonts) => {
        for (const fontFolder in loadedFonts) {
            const fontGlyphs = loadedFonts[fontFolder];
            for (const glyphName in fontGlyphs) {
                const expectedClass = `esi-${fontFolder.toLowerCase()}-${glyphName}`;
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
                <AlignmentToolbar
                    value={align}
                    onChange={handleAlignmentChange}
                />
            </BlockControls>

            <div {...blockProps} className={`${blockProps.className} ${wrapperClass}`}>
                {selectedIcon.className && isIconValid(selectedIcon.className, fonts) ? (
                    <button
                        className={selectedIcon.className}
                        style={{ cursor: 'pointer' }}
                        popovertarget={selectorID}
                    ></button>
                ) : (
                    <button popovertarget={selectorID}>
                        {__('No Icon Selected', 'easyiconfonts')}
                    </button>
                )}
            </div>

            {(
                <div className="esi-icon-grid" id={selectorID} popover="true">
                    <div className="esi-icon-search">
                        <TextControl
                            value={searchTerm}
                            onChange={(value) => setSearchTerm(value)}
                            placeholder={__('search icon by glyph name...', 'easyiconfonts')}
                        />
                    </div>

                    <div className="esi-icon-font-selects">
                    {loading && <p>{__('Loading fonts...', 'easyiconfonts')}</p>}
                    {error && <p>{__('Error: ', 'easyiconfonts')}{error}</p>}
                    {!loading && !error && filteredFonts.length > 0 && (
                        filteredFonts.map((font, index) => (
                            <details key={index} className="esi-font-details">
                                <summary>{font.fontFolder}</summary>
                                <div className="esi-font-icons">
                                    {font.glyphs.map(([name], i) => {
                                        const iconClass = `esi-${font.fontFolder.toLowerCase()}-${name}`;

                                        return (
                                            <span
                                                key={i}
                                                className="esi-font-icon"
                                                onClick={() => handleIconClick(iconClass)} // Select icon on click
                                                style={{ cursor: 'pointer', fontSize: '20px', margin: '5px' }}
                                            >
                                                <span className={iconClass}></span>
                                            </span>
                                        );
                                    })}
                                </div>
                            </details>
                        ))
                    )}
                    </div>

                    {!loading && !error && filteredFonts.length === 0 && (
                        <p>{__('No fonts found', 'easyiconfonts')}</p>
                    )}
                </div>
            )}
        </>
    );
}
