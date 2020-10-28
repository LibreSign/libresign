import SignerTab from './SignerTab'

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab('signer', SignerTab, (fileInfo) => {
			if (!fileInfo || fileInfo.isDirectory()) {
				return false
			}

			const mimetype = fileInfo.get('mimetype') || ''
			return mimetype === 'application/pdf'
		}))
	}
})
