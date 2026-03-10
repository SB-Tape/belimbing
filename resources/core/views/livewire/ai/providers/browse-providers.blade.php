<div>
    <x-slot name="title">{{ __('Browse Providers') }}</x-slot>

    @if($wizardStep === 'connect')
        <livewire:ai.providers.connect-wizard :initial-forms="$connectForms" />
    @else
        <livewire:ai.providers.catalog />
    @endif
</div>
