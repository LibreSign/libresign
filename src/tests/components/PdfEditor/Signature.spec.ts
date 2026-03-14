/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'

import Signature from '../../../components/PdfEditor/Signature.vue'

type ContainerStyle = {
	width: string
	height: string
	transform: string
}

type SignatureVm = {
	handlePanStart: (event: MouseEvent | TouchEvent) => void
	handlePanMove: (event: MouseEvent | TouchEvent) => void
	handlePanEnd: (event: MouseEvent | TouchEvent) => void
	containerStyle: ContainerStyle
	$nextTick: () => Promise<void>
}

type SignatureWrapper = VueWrapper<SignatureVm>

describe('PdfEditor Signature.vue', () => {
	let wrapper: SignatureWrapper

	function createWrapper(props: Record<string, unknown> = {}): SignatureWrapper {
		return mount(Signature, {
			props: {
				displayName: 'Ada Lovelace',
				width: 200,
				height: 100,
				x: 50,
				y: 60,
				originWidth: 200,
				originHeight: 100,
				pageScale: 1,
				...props,
			},
			global: {
				stubs: {
					NcIconSvgWrapper: true,
				},
			},
		}) as SignatureWrapper
	}

	beforeEach(() => {
		wrapper = createWrapper()
	})

	it('emits the initial scaled size on mount', async () => {
		const localWrapper = createWrapper({
			width: 800,
			height: 600,
			originWidth: 800,
			originHeight: 600,
		})

		await localWrapper.vm.$nextTick()

		expect(localWrapper.emitted('onUpdate')).toEqual([
			[{ width: 500, height: 375 }],
		])
	})

	it('updates translation while dragging and emits the final coordinates', async () => {
		const currentTarget = {} as unknown as EventTarget

		wrapper.vm.handlePanStart({
			type: 'mousedown',
			clientX: 10,
			clientY: 20,
			target: currentTarget,
			currentTarget,
		} as MouseEvent)
		wrapper.vm.handlePanMove({
			type: 'mousemove',
			clientX: 30,
			clientY: 50,
		} as MouseEvent)

		expect(wrapper.vm.containerStyle).toEqual({
			width: '200px',
			height: '100px',
			transform: 'translate(20px, 30px)',
		})

		wrapper.vm.handlePanEnd({
			type: 'mouseup',
			clientX: 30,
			clientY: 50,
		} as MouseEvent)
		await wrapper.vm.$nextTick()

		expect(wrapper.emitted('onUpdate')).toEqual([
			[{ width: 200, height: 100 }],
			[{ x: 70, y: 90 }],
		])
	})

	it('resizes from selector handles and emits updated bounds', async () => {
		const currentTarget = {} as unknown as EventTarget
		const target = {
			dataset: {
				direction: 'left-top',
			},
		} as unknown as EventTarget

		wrapper.vm.handlePanStart({
			type: 'mousedown',
			clientX: 100,
			clientY: 100,
			target,
			currentTarget,
		} as MouseEvent)
		wrapper.vm.handlePanMove({
			type: 'mousemove',
			clientX: 80,
			clientY: 70,
		} as MouseEvent)
		wrapper.vm.handlePanEnd({
			type: 'mouseup',
			clientX: 80,
			clientY: 70,
		} as MouseEvent)
		await wrapper.vm.$nextTick()

		expect(wrapper.emitted('onUpdate')).toEqual([
			[{ width: 200, height: 100 }],
			[{ x: 30, y: 30, width: 220, height: 110 }],
		])
	})

	it('emits onDelete when requested', async () => {
		await wrapper.find('.delete').trigger('click')

		expect(wrapper.emitted('onDelete')).toEqual([[]])
	})
})
