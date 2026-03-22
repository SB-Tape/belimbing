# Theme Customization Guide

**Document Type:** Developer Guide
**Audience:** BLB Adopters
**Last Updated:** 2026-02-09

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

**Create:** `resources/extensions/{licensee}/css/tokens.css` (adopter-specific, e.g. `resources/extensions/{licensee}/css/tokens.css`)

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
@import './{licensee}/css/tokens.css';  /* Adopter overrides */
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
1. resources/extensions/{licensee}/views/components/ui/button.blade.php  (adopter)
2. app/View/Components/Ui/Button.php                          (adopter class-based)
3. [BLB framework components]                                 (fallback)
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
- `resources/core/views/components/ui/button.blade.php`
- `resources/core/views/components/ui/card.blade.php`
- `resources/core/views/components/ui/input.blade.php`
- `resources/core/views/components/ui/modal.blade.php`
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

Override dark mode colors in `resources/extensions/{licensee}/css/tokens.css`:

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
- `AGENTS.md` - Coding conventions

---

**Summary:** BLB adopters can customize UI via CSS variables (colors/fonts) and component overrides (structure/layout), maintaining customizations across framework upgrades through git workflow.
