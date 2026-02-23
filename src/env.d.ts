/// <reference types="vite/client" />

declare module '*.vue' {
	import type { DefineComponent } from 'vue'
	const component: DefineComponent<{}, {}, any>
	export default component
}

declare module '@nextcloud/vue/dist/Components/*.js' {
	import type { DefineComponent } from 'vue'
	const component: DefineComponent
	export default component
}

declare module '@vue/runtime-core' {
	interface ComponentCustomProperties {
		$t: typeof import('@nextcloud/l10n').translate
		$n: typeof import('@nextcloud/l10n').translatePlural
		OC: any
		OCA: any
	}
}
