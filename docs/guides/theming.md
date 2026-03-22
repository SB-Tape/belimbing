# Theme Customization Guide

**Document Type:** Developer Guide
**Audience:** BLB Adopters
**Last Updated:** 2026-02-22

---

## Overview

BLB provides two primary mechanisms for adopters to customize the UI and maintain their customizations across BLB framework upgrades:

1. **CSS Custom Properties** - Theme colors, fonts, spacing
2. **Blade Component Override** - Complete component replacement

Both approaches are **git-friendly** and survive framework upgrades (adopters pull from upstream BLB without losing customizations).

---

## Method 1: CSS Custom Properties (Recommended for Colors/Fonts)

### How It Works

Tailwind CSS v4 uses CSS custom properties (`@theme`) for theming. BLB exposes theme variables that adopters can override.

### Default Theme

**File:** `resources/core/css/tokens.css`

```css
@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;

    /* Zinc color palette (neutrals) */
    --color-zinc-50: #fafafa;
    --color-zinc-100: #f5f5f5;
    --color-zinc-200: #e5e5e5;
    --color-zinc-300: #d4d4d4;
    --color-zinc-400: #a3a3a3;
    --color-zinc-500: #737373;
    --color-zinc-600: #525252;
    --color-zinc-700: #404040;
    --color-zinc-800: #262626;
    --color-zinc-900: #171717;
    --color-zinc-950: #0a0a0a;
}
```

### Customization Example

**Create:** `resources/extensions/{licensee}/css/tokens.css` (licensee-specific, e.g. `resources/extensions/{licensee}/css/tokens.css`)

```css
@theme {
    /* Brand font */
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    
    /* Brand primary color (replaces blue-600) */
    --color-blue-600: #7c3aed;  /* Purple brand */
    --color-blue-700: #6d28d9;
    
    /* Or create custom color */
    --color-brand: #7c3aed;
    --color-brand-hover: #6d28d9;
}
```

**Import in:** `resources/app.css`

```css
@import 'tailwindcss';
@import './{licensee}/css/tokens.css';  /* Licensee overrides */
```

### What Can Be Customized

| Property | Purpose | Example Override |
|----------|---------|------------------|
| `--font-sans` | Primary font family | 'Your Font', sans-serif |
| `--color-blue-*` | Primary/accent colors | #your-brand-color |
| `--color-zinc-*` | Neutral colors | #custom-gray |
| Custom colors | Brand-specific | `--color-brand: #ff6b35` |
| Spacing | Layout spacing | `--spacing-4: 1.5rem` |
| Border radius | Corner rounding | `--radius-lg: 1rem` |

### Usage in Tailwind

```blade
<!-- Uses --color-blue-600 -->
<button class="bg-blue-600 text-white">Click</button>

<!-- Uses custom --color-brand -->
<button class="bg-brand text-white">Click</button>
```

**Benefits:**
- ✅ Survives BLB upgrades (overrides in separate file)
- ✅ No PHP/Blade changes needed
- ✅ Instant theme changes (just edit CSS)
- ✅ Can switch themes via different CSS files

---

## Method 2: Blade Component Override (Recommended for Layout/Structure)

### How It Works

Laravel's component resolution checks **adopter directories first**, then framework directories. Adopters can override any BLB component by creating a file with the same name.

### Resolution Order

```
1. resources/extensions/{licensee}/views/components/ui/button.blade.php  (licensee)
2. app/View/Components/Ui/Button.php               (adopter class-based)
3. [BLB framework components]                      (fallback)
```

### Customization Example

**Override BLB's button component:**

**BLB Default:** Uses blue-600 for primary buttons

**Adopter Override:** Create `resources/extensions/{licensee}/views/components/ui/button.blade.php`

```blade
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button'
])

@php
$variantClasses = match($variant) {
    'primary' => 'bg-purple-600 hover:bg-purple-700 text-white',  // Purple instead of blue
    'secondary' => 'bg-zinc-600 hover:bg-zinc-700 text-white',
    'danger' => 'bg-red-600 hover:bg-red-700 text-white',
    default => 'bg-purple-600 hover:bg-purple-700 text-white',
};

$sizeClasses = match($size) {
    'sm' => 'px-3 py-1.5 text-sm',
    'md' => 'px-6 py-3 text-base',  // Larger default padding
    'lg' => 'px-8 py-4 text-lg',
    default => 'px-6 py-3 text-base',
};
@endphp

<button 
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-all {$variantClasses} {$sizeClasses}"]) }}
>
    {{ $slot }}
</button>
```

**Result:** All `<x-ui.button>` components now use adopter's purple theme and larger padding.

### What Can Be Overridden

**UI Components:**
- `resources/extensions/{licensee}/views/components/ui/button.blade.php`
- `resources/extensions/{licensee}/views/components/ui/card.blade.php`
- `resources/extensions/{licensee}/views/components/ui/input.blade.php`
- `resources/extensions/{licensee}/views/components/ui/modal.blade.php`
- All other `<x-ui.*>` components

**Layout Components:**
- `resources/core/views/components/layouts/top-bar.blade.php`
- `resources/core/views/components/layouts/status-bar.blade.php`
- `resources/core/views/components/menu/sidebar.blade.php`

**Menu Rendering:**
- `resources/core/views/components/menu/item.blade.php`
- `resources/core/views/components/menu/tree.blade.php`

**Benefits:**
- ✅ Complete control over component structure
- ✅ Survives BLB upgrades (adopter files take precedence)
- ✅ Can selectively override (only override what you need)
- ✅ Git-friendly (adopter customizations in their repo)

---

## Dark/Light Mode Toggle

### Implementation

BLB uses Tailwind's dark mode with class strategy (`class="dark"`).

**Add theme toggle button:**

```blade
<div x-data="{ 
    theme: localStorage.getItem('theme') || 'dark',
    init() {
        if (this.theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
}">
    <button 
        @click="
            theme = (theme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', theme);
            document.documentElement.classList.toggle('dark');
        "
        class="px-4 py-2 rounded-lg bg-zinc-200 dark:bg-zinc-800"
    >
        <span x-show="theme === 'dark'">☀️ Light Mode</span>
        <span x-show="theme === 'light'">🌙 Dark Mode</span>
    </button>
</div>
```

**Where to add:** Top bar, settings page, or user menu.

### Customizing Dark Mode Colors

Override dark mode colors in `resources/core/css/tokens.css`:

```css
@theme {
    /* Light mode colors */
    --color-zinc-900: #171717;
    
    /* Or create dark-specific variables */
    --color-dark-bg: #0a0a0a;
    --color-dark-text: #fafafa;
}
```

Then use: `bg-dark-bg dark:bg-dark-bg` or `text-zinc-900 dark:text-dark-text`

---

## Palette Preference

Users can choose the **main palette** (e.g. Arid Camouflage vs Neutral) as a personal preference. This section describes how to implement it so the feature works correctly now and when adding new palettes later.

### Goal

- User can select a main palette (e.g. Arid Camouflage, Neutral, or future options).
- Preference is persisted (logged-in: DB; guest: localStorage).
- The same semantic tokens are used everywhere; only their **values** change per palette.
- Palette choice is independent of light/dark mode (e.g. Arid + dark, Neutral + light).

### How it works

1. **One set of semantic tokens** — All UI uses the same names: `surface-page`, `surface-sidebar`, `surface-bar`, `accent`, `accent-hover`, `accent-on`, etc. (see `resources/core/css/tokens.css`).
2. **Palette = different values for the same tokens** — Each palette (e.g. `arid`, `neutral`) defines the **same** CSS custom properties with **different** primitive values. No class names in Blade change.
3. **Root attribute** — Apply the choice as a attribute on the root element (e.g. `data-palette="arid"` or `data-palette="neutral"` on `<html>`). CSS rules scoped to `[data-palette="…"]` override the default semantic token values.

### CSS structure

- **Default (e.g. Arid)** — Define semantic tokens in `@theme` (or `:root`) as today. This is the default palette.
- **Other palettes** — For each additional palette, add a block that redefines **only** the palette-dependent tokens:

```css
/* Default: Arid Camouflage (in @theme / :root) — values from theme primitives */
--color-surface-page: …;
--color-surface-sidebar: …;
--color-surface-bar: …;
--color-accent: …;
--color-accent-hover: …;
/* ... */

/* User preference: Neutral */
[data-palette="neutral"] {
  --color-surface-page: var(--color-zinc-100);
  --color-surface-sidebar: var(--color-zinc-200);
  --color-surface-bar: var(--color-zinc-200);
  --color-accent: var(--color-zinc-700);
  --color-accent-hover: var(--color-zinc-800);
  /* Only tokens that should change with palette; leave ink, muted, border-default, surface-card, etc. unless the palette defines them too */
}
```

- **Dark mode** — Keep using `.dark { ... }` to override semantic tokens for contrast. Palette and dark are independent: `.dark` can coexist with `[data-palette="neutral"]`; define `.dark` and, if needed, `[data-palette="neutral"].dark` overrides for any token that must differ when both apply.

### Which tokens are palette-dependent

- **Typically vary per palette:** `surface-page`, `surface-sidebar`, `surface-bar`, `accent`, `accent-hover`, `accent-on`. These define the "main" look (chrome + primary actions).
- **Typically fixed or shared:** `ink`, `muted`, `link`, `surface-card`, `surface-subtle`, `border-default`, `border-input` — unless a palette is designed to change the whole UI (e.g. full neutral theme). Document in `tokens.css` which tokens each palette overrides.

### Storing the preference

| User type   | Storage        | How to apply                                                                 |
|------------|----------------|-------------------------------------------------------------------------------|
| Logged-in  | User table     | Add column e.g. `preferred_palette` (`string`, nullable, default `'arid'`). On each request, output `data-palette="{{ auth()->user()->preferred_palette ?? 'arid' }}"` on `<html>`. Settings page: let user choose; update via Livewire and refresh or re-apply class. |
| Guest      | localStorage   | Key e.g. `blb.palette`. On load, a small script reads it and sets `document.documentElement.dataset.palette`. When guest changes palette in UI, write to localStorage and update `document.documentElement.dataset.palette`. |

Ensure the root element has `data-palette` set before first paint when possible (e.g. server-rendered for logged-in; script in `<head>` or early for guest) to avoid flash of wrong palette.

### Implementation checklist

- [ ] **Migration:** Add `preferred_palette` to users table (e.g. `arid`, `neutral`); default `arid`.
- [ ] **Root attribute:** In the main layout, set `<html data-palette="{{ auth()->user()->preferred_palette ?? 'arid' }}" ...>` (and ensure dark class is also set if using dark mode preference).
- [ ] **CSS:** In `resources/core/css/tokens.css`, add `[data-palette="neutral"] { ... }` (and any other palettes) with overrides for the palette-dependent semantic tokens. Add a short comment in `tokens.css` that palette options are documented in `docs/guides/theming.md`.
- [ ] **Settings UI:** Add a "Palette" or "Main theme" control (e.g. in appearance or profile settings). Options: Arid Camouflage, Neutral. On save, persist `preferred_palette` for logged-in users; for guest, persist to localStorage and set `data-palette` on `<html>`.
- [ ] **Guest script:** If guests can change palette, add a tiny script that reads `localStorage.getItem('blb.palette')` and sets `document.documentElement.dataset.palette` on load, and updates both when the user changes the option.

### Adding a new palette later

1. Define the new palette's primitive colors in `tokens.css` if needed (or reuse existing primitives).
2. Add a new block `[data-palette="new-name"] { ... }` that overrides the same semantic tokens (surface-page, surface-sidebar, surface-bar, accent, accent-hover, accent-on, and any others that should change).
3. Add the option to the settings UI and to the list of allowed values (e.g. in a config or enum).
4. Update this doc with the new palette name and any special notes (e.g. "Ocean uses blue primitives; define `--color-ocean-*` in theme").

### Palette references

- Semantic tokens and strategy: `resources/core/css/tokens.css` (comments), `.cursor/rules/ui-architect.mdc` (Semantic color strategy).
- Dark mode: same `tokens.css`, `.dark` block; ensure palette and dark can combine without conflicts.

---

## Combining Both Methods

**Best practice:** Use CSS Custom Properties for colors/fonts, Component Override for structure.

### Example: Purple Brand Theme

**Step 1: Override colors** (`resources/extensions/{licensee}/css/tokens.css`)

```css
@theme {
    --font-sans: 'Inter', sans-serif;
    --color-brand: #7c3aed;
    --color-brand-hover: #6d28d9;
    --color-blue-600: #7c3aed;  /* Replace default blue */
    --color-blue-700: #6d28d9;
}
```

**Step 2: Override button component** (`resources/extensions/{licensee}/views/components/ui/button.blade.php`)

```blade
@props(['variant' => 'primary'])

@php
$classes = match($variant) {
    'primary' => 'bg-brand hover:bg-brand-hover text-white shadow-lg',
    default => 'bg-brand hover:bg-brand-hover text-white',
};
@endphp

<button {{ $attributes->merge(['class' => "px-6 py-3 rounded-xl font-bold {$classes}"]) }}>
    {{ $slot }}
</button>
```

**Result:** All buttons use purple brand color with larger, bolder styling.

---

## Consistency Guidelines

### For BLB Framework Developers

**When creating new components:**

1. **Use semantic color names:** `bg-zinc-200 dark:bg-zinc-800` (not hardcoded hex)
2. **Expose customization points:** Use CSS variables where appropriate
3. **Document component props:** Make variants/sizes configurable
4. **Test in both modes:** Light and dark
5. **Follow established patterns:** Match existing component structure

### For Adopters

**To maintain consistency:**

1. **Override theme variables first:** Try CSS Custom Properties before component override
2. **Document your changes:** Add comments explaining why you overridden
3. **Test thoroughly:** Check all pages after customization
4. **Version control:** Commit theme files to your fork
5. **Review BLB updates:** Check if new components need theme application

---

## Git Workflow for Customizations

### Adopter Fork Strategy

```
BLB Upstream (main branch)
  ↓ fork
Adopter Repo (main branch)
  ↓ customization branch
Adopter Repo (customized)
  ↓ pull upstream updates
Merge conflicts resolved
```

**Customization files (adopter-owned):**
- `resources/extensions/{licensee}/css/tokens.css`
- `resources/extensions/{licensee}/views/components/ui/*.blade.php` (overrides)
- `config/theme.php` (if created)

**BLB framework files:**
- Pull updates from upstream
- Merge conflicts rare (custom files separate)

---

## Future: Theme Packages

**When BLB matures, adopters could publish themes:**

```bash
composer require acme/blb-theme-corporate
php artisan vendor:publish --tag=acme-theme
```

**Theme package contains:**
- CSS overrides
- Component overrides
- Logo/assets
- Color schemes

**This enables:**
- Community-contributed themes
- Professional theme marketplace
- Rapid branding for adopters

---

## Examples

### Example 1: Corporate Blue Theme

```css
@theme {
    --color-blue-600: #1e40af;  /* Darker corporate blue */
    --color-blue-700: #1e3a8a;
    --font-sans: 'Arial', sans-serif;  /* Conservative font */
}
```

### Example 2: Startup Purple Theme

```css
@theme {
    --color-blue-600: #7c3aed;  /* Purple */
    --color-blue-700: #6d28d9;
    --font-sans: 'Poppins', sans-serif;  /* Modern font */
    --radius-lg: 1rem;  /* More rounded corners */
}
```

### Example 3: Custom Button Style

**Override:** `resources/extensions/{licensee}/views/components/ui/button.blade.php`

```blade
{{-- Adopter: Larger, pill-shaped buttons for accessibility --}}
<button {{ $attributes->merge(['class' => 'px-8 py-4 rounded-full text-lg font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-xl transition-all hover:scale-105']) }}>
    {{ $slot }}
</button>
```

---

## Best Practices

1. **Start with CSS Custom Properties** - Try theme variables first
2. **Override components selectively** - Only override what you truly need different
3. **Maintain BLB patterns** - Keep similar structure for upgradability
4. **Document your theme** - Future you will thank you
5. **Test thoroughly** - Dark mode, light mode, all breakpoints

---

## Related Documents

- `docs/brief.md` - BLB's customization philosophy
- `docs/architecture/file-structure.md` - Module organization

---

**Summary:** BLB adopters can customize UI via CSS variables (colors/fonts) and component overrides (structure/layout), maintaining customizations across framework upgrades through git workflow.