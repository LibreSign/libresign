const rgxBegin = new RegExp('^/')
const rgxEnd = new RegExp('/$')

const pathJoin = (...parts) => {
	const s = parts.length - 1

	parts = parts.map((part, index) => {
		if (index) {
			part = part.replace(rgxBegin, '')
		}

		if (index !== s) {
			part = part.replace(rgxEnd, '')
		}

		return part
	})

	return parts.join('/')
}

export {
	pathJoin,
}
