// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

import './echo'

// Alpine.js - only initialize if not already loaded by Livewire
if (!globalThis.Alpine) {
    try {
        const module = await import('alpinejs')

        globalThis.Alpine = module.default
        globalThis.Alpine.start()
    } catch (error) {
        console.error('Failed to load Alpine.js.', error)
    }
}
