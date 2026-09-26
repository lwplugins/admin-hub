/**
 * The LW bolt knocked out of the blue square (lwplugins.com mark).
 *
 * @param {Object} props
 * @param {number} props.size Edge length in px.
 */
export default function BoltMark( { size = 28 } ) {
	return (
		<span
			className="lw-hub-mark"
			style={ { width: size, height: size } }
			aria-hidden="true"
		>
			<svg
				width={ size * 0.5 }
				height={ size * 0.58 }
				viewBox="112 108 224 296"
				xmlns="http://www.w3.org/2000/svg"
				focusable="false"
			>
				<path d="M128.6 272c-9.2 0-16.6-7.4-16.6-16.6 0-4.7 2-9.2 5.5-12.4L258.7 116.7c3.4-3 7.8-4.7 12.4-4.7 12.4 0 21.3 12 17.8 23.9l-31.2 104.1 61.8 0c9.2 0 16.6 7.4 16.6 16.6 0 4.7-2 9.2-5.5 12.4L189.3 395.3c-3.4 3-7.8 4.7-12.4 4.7-12.4 0-21.3-12-17.8-23.9l31.2-104.1-61.8 0z" />
			</svg>
		</span>
	);
}
