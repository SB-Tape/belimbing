@props(['variant' => 'default'])

@php
$variantClasses = match($variant) {
    'success' => 'bg-status-success-subtle text-status-success',
    'danger' => 'bg-status-danger-subtle text-status-danger',
    'warning' => 'bg-status-warning-subtle text-status-warning',
    'info' => 'bg-status-info-subtle text-status-info',
    'accent' => 'bg-accent/10 text-accent',
    default => 'bg-surface-subtle text-ink',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$variantClasses}"]) }}>
    {{ $slot }}
</span>
