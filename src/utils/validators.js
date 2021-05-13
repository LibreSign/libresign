export const validateEmail = (email) => {
	const reg = /^\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/
	return !!reg.test(email)
}
