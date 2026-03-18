<div>
    <x-slot name="title">{{ __('Create Company') }}</x-slot>

    <div class="space-y-section-gap">
        <x-ui.page-header :title="__('Create Company')" :subtitle="__('Add a company record and business context')">
            <x-slot name="actions">
                <a href="{{ route('admin.companies.index') }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-accent hover:bg-surface-subtle transition-colors">
                    <x-icon name="heroicon-o-arrow-left" class="w-5 h-5" />
                    {{ __('Back') }}
                </a>
            </x-slot>
        </x-ui.page-header>

        <x-ui.card>
            <form wire:submit="store" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.select id="company-parent" wire:model="parentId" label="{{ __('Parent Company') }}" :error="$errors->first('parentId')">
                        <option value="">{{ __('None') }}</option>
                        @foreach($parentCompanies as $parentCompany)
                            <option value="{{ $parentCompany->id }}">{{ $parentCompany->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select id="company-status" wire:model="status" label="{{ __('Status') }}" :error="$errors->first('status')">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="suspended">{{ __('Suspended') }}</option>
                        <option value="pending">{{ __('Pending') }}</option>
                        <option value="archived">{{ __('Archived') }}</option>
                    </x-ui.select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.input
                        wire:model="name"
                        label="{{ __('Name') }}"
                        type="text"
                        required
                        placeholder="{{ __('Company display name') }}"
                        :error="$errors->first('name')"
                    />

                    <x-ui.input
                        wire:model="code"
                        label="{{ __('Code') }}"
                        type="text"
                        placeholder="{{ __('Auto-generated if blank') }}"
                        :error="$errors->first('code')"
                    />

                    <x-ui.input
                        wire:model="legalName"
                        label="{{ __('Legal Name') }}"
                        type="text"
                        placeholder="{{ __('Registered legal entity name') }}"
                        :error="$errors->first('legalName')"
                    />

                    <x-ui.select id="company-legal-entity-type" wire:model="legalEntityTypeId" label="{{ __('Legal Entity Type') }}" :error="$errors->first('legalEntityTypeId')">
                        <option value="">{{ __('Select type...') }}</option>
                        @foreach($legalEntityTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input
                        wire:model="registrationNumber"
                        label="{{ __('Registration Number') }}"
                        type="text"
                        placeholder="{{ __('Business registration number') }}"
                        :error="$errors->first('registrationNumber')"
                    />

                    <x-ui.input
                        wire:model="taxId"
                        label="{{ __('Tax ID') }}"
                        type="text"
                        placeholder="{{ __('Tax identification number') }}"
                        :error="$errors->first('taxId')"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-ui.select id="company-jurisdiction" wire:model="jurisdiction" label="{{ __('Jurisdiction') }}" :error="$errors->first('jurisdiction')">
                        <option value="">{{ __('Select country...') }}</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->iso }}">{{ $country->country }} ({{ $country->iso }})</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input
                        wire:model="email"
                        label="{{ __('Email') }}"
                        type="email"
                        placeholder="{{ __('Company contact email') }}"
                        :error="$errors->first('email')"
                    />

                    <x-ui.input
                        wire:model="website"
                        label="{{ __('Website') }}"
                        type="text"
                        placeholder="{{ __('example.com') }}"
                        :error="$errors->first('website')"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.textarea
                        wire:model="scopeActivitiesJson"
                        label="{{ __('Business Activities (JSON)') }}"
                        rows="6"
                        placeholder="{{ __('{\"industry\":\"Manufacturing\",\"services\":[\"Shipping\"],\"business_focus\":\"Regional trade\"}') }}"
                        :error="$errors->first('scopeActivitiesJson')"
                    />

                    <x-ui.textarea
                        wire:model="metadataJson"
                        label="{{ __('Metadata (JSON)') }}"
                        rows="6"
                        placeholder="{{ __('{\"employee_count\":120,\"founded_year\":2014}') }}"
                        :error="$errors->first('metadataJson')"
                    />
                </div>

                <div class="flex items-center gap-4">
                    <x-ui.button type="submit" variant="primary">
                        {{ __('Create Company') }}
                    </x-ui.button>
                    <a href="{{ route('admin.companies.index') }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-accent hover:bg-surface-subtle transition-colors">
                        {{ __('Cancel') }}
                    </a>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
