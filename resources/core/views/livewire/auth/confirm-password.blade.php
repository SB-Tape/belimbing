<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Confirm password')"
        :description="__('This is a secure area of the application. Please confirm your password before continuing.')"
    />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" wire:submit="confirmPassword" class="flex flex-col gap-6">
        <!-- Password -->
        <x-ui.input
            id="confirm-password"
            wire:model="password"
            label="{{ __('Password') }}"
            type="password"
            required
            autocomplete="new-password"
            placeholder="{{ __('Password') }}"
        />

        <x-ui.button type="submit" variant="primary" class="w-full" data-test="confirm-password-button">
            {{ __('Confirm') }}
        </x-ui.button>
    </form>
</div>
