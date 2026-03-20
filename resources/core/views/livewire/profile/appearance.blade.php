<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div class="flex gap-2">
            <x-ui.radio id="theme-light" wire:model="theme" value="light" label="{{ __('Light') }}" />
            <x-ui.radio id="theme-dark" wire:model="theme" value="dark" label="{{ __('Dark') }}" />
            <x-ui.radio id="theme-system" wire:model="theme" value="system" label="{{ __('System') }}" />
        </div>
    </x-settings.layout>
</section>
