import Toast from './Toast'

export default Toast

export function toastVar(title, content) {
	const tt = `<Toast title="${title}" content="${content}" />`
	// eslint-disable-next-line
	console.log(tt)
}
