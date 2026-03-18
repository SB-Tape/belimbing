@props(['user'])

<div class="dropdown dropdown-end">
    <button type="button" class="w-10 h-10 rounded-full inline-flex items-center justify-center hover:bg-surface-subtle transition-colors" aria-label="{{ __('Open user menu') }}">
        <div class="w-10 rounded-full bg-surface-subtle flex items-center justify-center">
            <span class="text-sm font-semibold text-ink">{{ $user->initials() }}</span>
        </div>
    </button>
    <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-surface-card rounded-box w-52">
        <li class="disabled">
            <div class="flex flex-col items-start">
                <span class="font-semibold text-ink">{{ $user->name }}</span>
                <span class="text-xs text-muted">{{ $user->email }}</span>
            </div>
        </li>
        <li><hr class="my-1" /></li>
        <li>
            <a href="{{ route('profile.edit') }}" wire:navigate>
                <x-icon name="heroicon-o-cog-6-tooth" class="w-4 h-4" />
                {{ __('Settings') }}
            </a>
        </li>
        <li><hr class="my-1" /></li>
        <li>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left" data-test="logout-button">
                    <x-icon name="heroicon-o-arrow-right-on-rectangle" class="w-4 h-4" />
                    {{ __('Log Out') }}
                </button>
            </form>
        </li>
    </ul>
</div>
