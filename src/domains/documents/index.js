export { default as documentsService } from './service.js'

export const parseDocument = (entry) => {
	const { file } = entry

	return {
		uuid: file.uuid,
		nodeId: file.file.nodeId,
		file_type: entry.file_type,
		name: file.name,
		status: file.status,
		status_text: file.status_text,
	}
}
