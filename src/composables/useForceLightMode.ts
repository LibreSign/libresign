import { onMounted, onBeforeUnmount } from 'vue'

export function useForceLightMode(): void {
    let observer: MutationObserver | null = null

    const stripAttributes = (element: HTMLElement | null): void => {
        if (!element) return

        if (element.hasAttribute('data-theme-dark')) {
            element.removeAttribute('data-theme-dark')
        }
        if (element.getAttribute('data-themes') === 'dark') {
            element.removeAttribute('data-themes')
        }
        if (element.classList.contains('theme-dark')) {
            element.classList.remove('theme-dark')
        }
    }

    onMounted(() => {
        const targetBody = (document.getElementById('body-user') || document.body) as HTMLElement | null
        if (!targetBody) return

        // 1. Instant execution upon mount
        stripAttributes(targetBody)

        // 2. Setup the MutationObserver watchdog
        observer = new MutationObserver((mutations: MutationRecord[]) => {
            for (const mutation of mutations) {
                if (mutation.type === 'attributes' && observer) {
                    // Pause observer to prevent an infinite loop while modifying the DOM
                    observer.disconnect()

                    stripAttributes(targetBody)

                    // Resume watching
                    startObserving(targetBody)
                }
            }
        })

        const startObserving = (element: HTMLElement): void => {
            observer?.observe(element, {
                attributes: true,
                attributeFilter: ['data-theme-dark', 'data-themes', 'class']
            })
        }

        // Start the watchdog
        startObserving(targetBody)
    })

    // Automatically clean up memory when the component utilizing this composable is destroyed
    onBeforeUnmount(() => {
        if (observer) {
            observer.disconnect()
        }
    })
}
