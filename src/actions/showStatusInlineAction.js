import SignatureSvg from '@mdi/svg/svg/signature.svg'

import { FileAction, registerFileAction } from '@nextcloud/files'

const action = new FileAction({
	id: 'show-status-inline',
	displayName: () => '',
	title: () => 'Status',
	exec: async () => null,
	iconSvgInline: () => SignatureSvg,
	inline: () => true,
	order: -1,
})

registerFileAction(action)
