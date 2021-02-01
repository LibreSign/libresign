import LibresignTab from './views/LibresignTab'

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab('libresign', LibresignTab, (fileInfo) => {
			if (!fileInfo || fileInfo.isDirectory()) {
				return false
			}

			const mimetype = fileInfo.get('mimetype') || ''
			return mimetype === 'application/pdf'
		}))
	}
})
