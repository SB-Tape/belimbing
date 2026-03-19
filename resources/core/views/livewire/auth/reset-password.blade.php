<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" wire:submit="resetPassword" class="flex flex-col gap-6">
        <!-- Email Address -->
        <x-ui.input
            id="reset-email"
            wire:model="email"
            label="{{ __('Email') }}"
            type="email"
            required
            autocomplete="email"
        />

        <!-- Password -->
        <x-ui.input
            id="reset-password"
            wire:model="password"
            label="{{ __('Password') }}"
            type="password"
            required
            autocomplete="new-password"
            placeholder="{{ __('Password') }}"
        />

        <!-- Confirm Password -->
        <x-ui.input
            id="reset-password-confirmation"
            wire:model="passwordConfirmation"
            label="{{ __('Confirm password') }}"
            type="password"
            required
            autocomplete="new-password"
            placeholder="{{ __('Confirm password') }}"
        />

        <div class="flex items-center justify-end">
            <x-ui.button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                {{ __('Reset password') }}
            </x-ui.button>
        </div>
    </form>
</div>
