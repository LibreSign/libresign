export const isExternal = (to, from) => {
	if (from.path === '/') {
		return to.path.startsWith('/p/')
	}
	return from.path.startsWith('/p/')
}
