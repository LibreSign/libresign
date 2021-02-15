const redirectURL = OC.appConfig.libresign

const selectAction = (action) => {
	switch (action) {
	case 100:
		return window.location.href(redirectURL.url)
	case 150:
		return 'CreateUser'
	case 200:
		return 'DefaultPageError'
	case 250:
		return 'SignPDF'
	default:
		break
	}
}

export default selectAction
