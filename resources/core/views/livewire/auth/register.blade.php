<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <x-ui.input
            id="register-name"
            wire:model="name"
            label="{{ __('Name') }}"
            type="text"
            required
            autofocus
            autocomplete="name"
            placeholder="{{ __('Full name') }}"
        />

        <!-- Email Address -->
        <x-ui.input
            id="register-email"
            wire:model="email"
            label="{{ __('Email address') }}"
            type="email"
            required
            autocomplete="email"
            placeholder="email@example.com"
        />

        <!-- Password -->
        <x-ui.input
            id="register-password"
            wire:model="password"
            label="{{ __('Password') }}"
            type="password"
            required
            autocomplete="new-password"
            placeholder="{{ __('Password') }}"
        />

        <!-- Confirm Password -->
        <x-ui.input
            id="register-password-confirmation"
            wire:model="passwordConfirmation"
            label="{{ __('Confirm password') }}"
            type="password"
            required
            autocomplete="new-password"
            placeholder="{{ __('Confirm password') }}"
        />

        <div class="flex items-center justify-end">
            <x-ui.button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                {{ __('Create account') }}
            </x-ui.button>
        </div>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-muted">
        <span>{{ __('Already have an account?') }}</span>
        <a href="{{ route('login') }}" wire:navigate class="text-primary hover:underline">{{ __('Log in') }}</a>
    </div>
</div>
