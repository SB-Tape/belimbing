@props(['title' => null])

<div class="drawer lg:drawer-open">
    <input id="sidebar-drawer" type="checkbox" class="drawer-toggle" />

    <div class="drawer-content flex flex-col">
        <!-- Page content here -->
        {{ $slot }}
    </div>

    <div class="drawer-side">
        <label for="sidebar-drawer" aria-label="{{ __('Close navigation') }}" class="drawer-overlay"></label>
        <aside class="w-64 min-h-full bg-surface-subtle border-r border-border-default">
            {{ $sidebar ?? '' }}
        </aside>
    </div>
</div>

