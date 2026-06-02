<template>
	<div class="page">

		<div class="products-wrapper">

			<!-- PAGE HEADER -->
			<div class="page-header">
				<div class="page-header-top">
					<h2>Products</h2>
				</div>

				<p class="page-subtitle">
					Products define what users can purchase to perform actions (e.g. signing documents).
					Each purchase grants entitlements that allow access to those actions.
				</p>
			</div>

			<!-- ===================== -->
			<!-- LOADING STATE -->
			<!-- ===================== -->
			<div v-if="loadingProducts" class="card table-card">
				<div class="skeleton-table">
					<div v-for="i in 3" :key="i" class="skeleton-row"></div>
				</div>
			</div>

			<!-- ===================== -->
			<!-- DATA STATE -->
			<!-- ===================== -->
			<template v-else-if="hasProducts">

				<!-- PRODUCT GROUPS -->
				<div class="product-groups-section">
					<div class="chips-header">
						<span class="chips-label">Product groups</span>
						<span class="chips-hint">
							Each group represents an action/product code (e.g. SIGN_DOCUMENT)
						</span>
					</div>

					<div class="chips">
						<span v-for="code in productCodes" :key="code" class="chip">
							{{ code }}
						</span>
					</div>
				</div>

				<!-- PRODUCTS TABLE -->
				<div class="card table-card">

					<div class="card-header">
						<div class="card-header-left">
							<h3>Available Products</h3>
							<span class="card-subtitle">
								{{ products.length }} product{{ products.length !== 1 ? 's' : '' }}
							</span>
						</div>

						<div class="card-header-right">
							<NcButton @click="openCreateModal">
								<template #icon>
									<NcIconSvgWrapper :path="mdiPlus" :size="18" />
								</template>
								New Product
							</NcButton>
						</div>
					</div>

					<div class="table-container">
						<table class="table">
							<thead>
								<tr>
									<th>Code</th>
									<th>Amount</th>
									<th>Uses</th>
									<th>Status</th>
									<th>Default</th>
									<th>Actions</th>
								</tr>
							</thead>

							<tbody>
								<tr v-for="p in products" :key="p.id">

									<td>{{ p.code }}</td>

									<td>{{ formatAmount(p.amount) }}</td>

									<td>{{ p.uses }}</td>

									<td>
										<span :class="['badge', p.active ? 'active' : 'inactive']">
											{{ p.active ? 'Active' : 'Inactive' }}
										</span>
									</td>

									<td>
										<span v-if="p.isDefault" class="default-pill">Default</span>
										<span v-else class="muted">—</span>
									</td>

									<td>
										<NcActions>

											<NcActionButton @click="editProduct(p)">
												<template #icon>
													<NcIconSvgWrapper :path="mdiPencil" :size="16" />
												</template>
												Edit
											</NcActionButton>

											<NcActionButton v-if="!p.is_default" @click="setActive(p)">
												<template #icon>
													<NcIconSvgWrapper :path="mdiStarOutline" :size="16" />
												</template>
												Set Active
											</NcActionButton>

											<NcActionButton v-if="!p.is_default" @click="setDefault(p)">
												<template #icon>
													<NcIconSvgWrapper :path="mdiCheckDecagramOutline" :size="16" />
												</template>
												Set Default
											</NcActionButton>

											<NcActionButton @click="handleDelete()" variant="error">
												<template #icon>
													<NcIconSvgWrapper :path="mdiDeleteOutline" :size="16" />
												</template>
												Delete
											</NcActionButton>

										</NcActions>
									</td>

								</tr>
							</tbody>
						</table>
					</div>

				</div>
			</template>

			<!-- ===================== -->
			<!-- EMPTY STATE -->
			<!-- ===================== -->
			<div v-else class="empty-state">

				<NcIconSvgWrapper :path="mdiPackageVariant" :size="48" />

				<h3>No products yet</h3>

				<p>
					Create your first product to allow users to perform actions like signing documents.
				</p>

				<NcButton @click="openCreateModal">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPlus" :size="18" />
					</template>
					Create Product
				</NcButton>

			</div>

			<!-- ===================== -->
			<!-- CREATE MODAL -->
			<!-- ===================== -->
			<ProductModal v-model="showCreateModal" mode="create" @success="loadProducts" />

			<!-- ===================== -->
			<!-- EDIT MODAL -->
			<!-- ===================== -->
			<ProductModal v-model="showEditModal" mode="edit" :product="selectedProduct" @success="loadProducts" />

			<!-- ===================== -->
			<!-- CONFIRM DIALOG -->
			<!-- ===================== -->
			<ConfirmDialog v-model="showConfirmDialog"
				:title="confirmAction === 'delete' ? 'Delete Product' : confirmAction === 'default' ? 'Set Default Product' : 'Toggle Active Status'"
				:message="confirmAction === 'delete'
						? 'Are you sure you want to delete this product? This action cannot be undone.'
						: confirmAction === 'default'
							? 'Set this product as the default for this action?'
							: 'Are you sure you want to toggle the active status of this product?'
					" :confirmText="confirmAction === 'delete' ? 'Delete' : confirmAction === 'default' ? 'Set Default' : 'Toggle Active'"
				:destructive="confirmAction === 'delete'" :loading="confirmLoading" @confirm="handleConfirm" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiPencil,
	mdiStarOutline,
	mdiDeleteOutline,
	mdiPlus,
	mdiPackageVariant,
	mdiCheckDecagramOutline
} from '@mdi/js'

import { notifyInfo, notifySuccess, notifyError } from '@/services/toast'
import ProductModal from './modals/ProductModal.vue'
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue'

// state
const products = ref<any[]>([])
const loadingProducts = ref(false)
const showCreateModal = ref(false)
const showEditModal = ref(false)
const selectedProduct = ref<any | null>(null)
const showConfirmDialog = ref(false)
const confirmAction = ref<'delete' | 'default' | 'active' | null>(null)
const confirmLoading = ref(false)
const canDelete = false;

const hasProducts = computed(() => products.value.length > 0)

const productCodes = computed(() => {
	return [...new Set(products.value.map(p => p.code))]
})

async function loadProducts() {
	try {
		loadingProducts.value = true

		const { data } = await axios.get(
			generateOcsUrl('/apps/libresign/api/v1/product/list-all')
		)

		products.value = data?.ocs?.data?.products ?? []

	} catch (err) {
		notifyError({
			message: 'Failed to load products',
			important: true,
		})
	} finally {
		loadingProducts.value = false
	}
}

async function handleDelete() {
	notifyInfo({
		message: `Delete product will be implemented in a future release.`
	})
}

async function handleConfirm() {
	if (!selectedProduct.value) return

	try {
		confirmLoading.value = true

		if (confirmAction.value === 'delete') {
			await axios.delete(
				generateOcsUrl(`/apps/libresign/api/v1/admin/products/${selectedProduct.value.id}`)
			)

			notifySuccess({ message: 'Product deleted successfully' })

		} else if (confirmAction.value === 'default') {
			await axios.post(
				generateOcsUrl('/apps/libresign/api/v1/product/set-default'),
				{ productId: selectedProduct.value.id }
			)

			notifySuccess({ message: 'Default product updated' })
		} else if (confirmAction.value === 'active') {
			await axios.post(
				generateOcsUrl('/apps/libresign/api/v1/product/set-active'),
				{ productId: selectedProduct.value.id, active: !selectedProduct.value.active }
			)

			notifySuccess({ message: `Product ${selectedProduct.value.active ? 'deactivated' : 'activated'} successfully` })
		}

		showConfirmDialog.value = false
		selectedProduct.value = null
		confirmAction.value = null

		await loadProducts()

	} catch (err) {
		const action = confirmAction.value === 'delete' ? 'Delete product' : 'Set default product'
		notifyError({
			message: `Failed to ${action}`,
			important: true,
		})
	} finally {
		confirmLoading.value = false
	}
}

function setDefault(product: any) {
	selectedProduct.value = product
	confirmAction.value = 'default'
	showConfirmDialog.value = true
}

function setActive(product: any) {
	selectedProduct.value = product
	confirmAction.value = 'active'
	showConfirmDialog.value = true
}

function formatAmount(amount: number, currency: string = 'KES') {
	return `${(amount / 100).toFixed(2)} ${currency}`
}

function openCreateModal() {
	showCreateModal.value = true
}

function editProduct(product: any) {
	selectedProduct.value = product
	showEditModal.value = true
}

function deleteProduct(product: any) {
	selectedProduct.value = product
	confirmAction.value = 'delete'
	showConfirmDialog.value = true
}

onMounted(() => {
	loadProducts()
})
</script>

<style scoped>
.page {
	padding: 32px;
}

.page-header {
	margin-bottom: 24px;
}

.page-header-top {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.page-header-top h2 {
	margin-bottom: 4px;
}

.page-subtitle {
	margin-top: 6px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	max-width: 720px;
}

.nc-action-button svg {
	margin-right: 6px;
}

.card {
	width: 100%;

	padding: 24px;
	border-radius: 12px;

	background: var(--color-main-background);
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);

	border: 1px solid var(--color-border);
}

.card-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
}

.card-header-left {
	display: flex;
	flex-direction: column;
}

.card-header-left h3 {
	margin-top: 0px;
	margin-bottom: 2px;
}

.card-header-right {
	display: flex;
	align-items: center;
}

.card-subtitle {
	font-size: 12px;
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
}

.products-wrapper {
	width: 100%;
	max-width: 1000px;
	margin: 0 auto;
}

.product-groups-section {
	margin-bottom: 20px;
}

.table-card {
	width: 100%;
	padding: 16px;
	border-radius: 12px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	margin-top: 12px;
}

.container {
	padding: 20px;
	max-width: 500px;
}

.form-container {
	width: 100%;
	display: flex;
	justify-content: center;
}

.products-table {
	margin-top: 40px;
}

.table-container {
	overflow-x: auto;
}

.table {
	width: 100%;
	border-collapse: collapse;
	border: 1px solid var(--color-border);
}

.table tbody tr:hover {
	background: var(--color-background-hover);
}

.table th,
.table td {
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
}

.table th {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.table td {
	font-size: 14px;
}

.badge {
	padding: 4px 10px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 500;

}

.badge.active {
	background: var(--gp-success-bg);
	color: var(--gp-success-text);
}

.badge.inactive {
	background: var(--gp-error-bg);
	color: var(--gp-error-text);
}

.default-label {
	font-weight: bold;
	color: var(--color-primary);
}

.products-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
}

.modal-body {
	padding: 20px;
}

.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 20px;
}

.chips-header {
	display: flex;
	flex-direction: column;
	margin-bottom: 6px;
}

.chips-label {
	font-weight: 600;
	font-size: 13px;
	margin-bottom: 2px;
}

.chips-hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.chips {
	display: flex;
	gap: 8px;
	margin: 8px 0 16px;
}

.chip {
	padding: 4px 10px;
	border-radius: 12px;

	background: var(--color-background-hover);
	border: 1px solid var(--color-border);

	font-size: 12px;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
}

.chip.active,
.default-pill {
	background: var(--gp-success-bg);
	color: var(--gp-success-text);
}

.default-pill {
	padding: 4px 10px;
	border-radius: 12px;
	font-size: 12px;
}

.muted {
	color: var(--color-text-maxcontrast);
}

.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	padding: 40px 20px;
	text-align: center;
	gap: 12px;

	color: var(--color-text-maxcontrast);
}

.empty-state h3 {
	margin: 0;
}

.empty-state p {
	max-width: 400px;
	font-size: 13px;
}

.skeleton-row {
	height: 40px;
	margin-bottom: 8px;
	border-radius: 6px;
	background: var(--color-background-hover);
}

.skeleton-table {
	padding: 8px;
}
</style>
