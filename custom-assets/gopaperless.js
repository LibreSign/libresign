(function () {
	"use strict"

	const APP_NAME = "GoPaperless"
	const MODAL_STORAGE_KEY = "gopaperless-onboarding-seen"

	/*
	|--------------------------------------------------------------------------
	| Social Links Config. /apps-extra/libresign/img/{file-name}.svg
	|--------------------------------------------------------------------------
	*/

	const SOCIAL_LINKS = [
		{
			name: "Facebook",
			url: "https://web.facebook.com/tendaworld/",
			icon: "/apps-extra/libresign/img/facebook.png",
		},
		{
			name: "TikTok",
			url: "https://www.tiktok.com/@tendaworld",
			icon: "/apps-extra/libresign/img/tiktok.png",
		},
		{
			name: "X",
			url: "https://x.com/tenda_world",
			icon: "/apps-extra/libresign/img/x.png",
		},
		{
			name: "LinkedIn",
			url: "https://www.linkedin.com/company/tenda-world/",
			icon: "/apps-extra/libresign/img/linkedin.png",
		},
	]

	/*
	|--------------------------------------------------------------------------
	| Title Update
	|--------------------------------------------------------------------------
	*/

	function updatePageTitle() {
		let title = document.title

		if (title.startsWith("LibreSign - ")) {
			title = title.replace(
				"LibreSign - ",
				`${APP_NAME} - Upload and sign documents`
			)

			document.title = title
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Search Label Update
	|--------------------------------------------------------------------------
	*/

	function updateSearchLabel(root = document) {
		const input = root.querySelector('[data-cy-unified-search-input]')
		if (!input) return

		const label = input
			.closest(".input-field")
			?.querySelector(".input-field__label")

		if (label && label.textContent !== "Search documents...") {
			label.textContent = "Search documents..."
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Dashboard Text
	|--------------------------------------------------------------------------
	*/

	function updateDashboardText() {
		const description = document.querySelector("#container-request header p")

		if (description && description.textContent.includes("Choose the file")) {
			description.textContent = "Upload a document to begin signing."
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Social Icon Component
	|--------------------------------------------------------------------------
	*/

	function createSocialIcon({ name, url, icon }) {
		const link = document.createElement("a")

		link.href = url
		link.target = "_blank"
		link.rel = "noreferrer noopener"

		link.innerHTML = `
			<img
				width="50"
				height="50"
				src="${icon}"
				title="Follow Tendaworld on ${name}"
				alt="Follow Tendaworld on ${name}"
			>
		`

		return link
	}

	function renderSocialLinks(container) {
		container.innerHTML = ""

		SOCIAL_LINKS.forEach((social) => {
			container.appendChild(createSocialIcon(social))
		})
	}

	/*
	|--------------------------------------------------------------------------
	| Settings Page Branding
	|--------------------------------------------------------------------------
	*/

	function updateSettingsBranding() {

		const isSettingsRoute =
			window.location.pathname.startsWith("/settings/user")

		if (!isSettingsRoute) return

		const section = document.querySelector(".section.development-notice")
		if (!section) return

		if (section.dataset.gopaperlessModified) return

		/* Update Learn More link */

		const link = section.querySelector("#open-reasons-use-nextcloud-pdf")

		if (link) {
			link.textContent = "Learn how GoPaperless works"
			link.href = "https://gopaperless.ke"
			link.target = "_blank"
		}

		/* Update Developed by */

		const paragraphs = section.querySelectorAll("p")

		if (paragraphs.length > 1) {

			paragraphs[1].innerHTML = `
				Developed by <a href="https://tendaworld.com" target="_blank" rel="noopener noreferrer"><strong>Tendaworld</strong></a>.
			`
		}

		/* Render social icons */

		const socialContainer = section.querySelector(".social-button")

		if (socialContainer) {
			renderSocialLinks(socialContainer)
		}

		section.dataset.gopaperlessModified = "true"
	}

	/*
	|--------------------------------------------------------------------------
	| Onboarding Modal
	|--------------------------------------------------------------------------
	*/

	function showOnboardingModal() {

		if (localStorage.getItem(MODAL_STORAGE_KEY)) return

		const modal = document.createElement("div")

		modal.className = "gopaperless-onboarding"

		modal.innerHTML = `
			<div class="gopaperless-onboarding-card">

				<button class="gopaperless-close">×</button>

				<div class="gopaperless-onboarding-header">

					<div class="gopaperless-illustration">

						<svg width="220" height="160" viewBox="0 0 220 160"
						xmlns="http://www.w3.org/2000/svg">

						<ellipse cx="110" cy="90" rx="95" ry="55"
						fill="#eaf5ef"/>

						<rect x="60" y="25" width="85" height="110"
						rx="8" fill="#cfe7dc"/>

						<rect x="70" y="20" width="85" height="110"
						rx="8" fill="#dff0e7"/>

						<rect x="80" y="15" width="85" height="110"
						rx="8" fill="#ffffff" stroke="#d6e4dd"/>

						<path d="M150 15 L165 30 L150 30 Z"
						fill="#1c8f55"/>

						<circle cx="180" cy="100" r="16"
						fill="#1c8f55"/>

						<path d="M173 100 L178 105 L187 94"
						stroke="white" stroke-width="3"
						fill="none"/>

						</svg>

					</div>

					<h2>Welcome to GoPaperless</h2>

					<p>Send documents for signature in 3 simple steps.</p>

				</div>

				<div class="gopaperless-steps">

					<div class="gopaperless-step">
						<div class="gopaperless-step-icon">⬆</div>
						<div>
							<strong>Upload a document</strong>
							<span>Drag, upload, or choose from files</span>
						</div>
					</div>

					<div class="gopaperless-step">
						<div class="gopaperless-step-icon">👤</div>
						<div>
							<strong>Add a signer</strong>
							<span>Invite people to sign</span>
						</div>
					</div>

					<div class="gopaperless-step">
						<div class="gopaperless-step-icon">✉</div>
						<div>
							<strong>Send for signature</strong>
							<span>Track signing progress</span>
						</div>
					</div>

				</div>

				<button class="gopaperless-start">
					Proceed →
				</button>

			</div>
		`

		document.body.appendChild(modal)

		function closeModal() {
			localStorage.setItem(MODAL_STORAGE_KEY, "true")
			modal.remove()
		}

		modal.querySelector(".gopaperless-close").onclick = closeModal
		modal.querySelector(".gopaperless-start").onclick = closeModal
	}

	function maybeShowOnboarding() {

		const isDashboardRoute =
			window.location.pathname.startsWith("/apps/libresign/f/request")

		if (!isDashboardRoute) return

		if (localStorage.getItem(MODAL_STORAGE_KEY)) return

		showOnboardingModal()
	}

	/*
	|--------------------------------------------------------------------------
	| Mutation Observer
	|--------------------------------------------------------------------------
	*/

	const observer = new MutationObserver((mutations) => {

		updatePageTitle()

		for (const mutation of mutations) {

			for (const node of mutation.addedNodes) {

				if (!(node instanceof HTMLElement)) continue

				const searchModal =
					node.id === "unified-search"
						? node
						: node.querySelector?.("#unified-search")

				if (searchModal) {
					setTimeout(() => updateSearchLabel(searchModal), 0)
				}
			}
		}
	})

	observer.observe(document.body, {
		childList: true,
		subtree: true,
	})

	/*
	|--------------------------------------------------------------------------
	| Initial Load
	|--------------------------------------------------------------------------
	*/

	document.addEventListener("DOMContentLoaded", () => {
		maybeShowOnboarding()
	})

})()
