<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\Company\Livewire\Companies;

use App\Base\Foundation\Livewire\Concerns\DecodesJsonFields;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\LegalEntityType;
use App\Modules\Core\Geonames\Models\Country;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Create extends Component
{
    use DecodesJsonFields;

    public ?int $parent_id = null;

    public string $name = '';

    public ?string $code = null;

    public string $status = 'active';

    public ?string $legal_name = null;

    public ?string $registration_number = null;

    public ?string $tax_id = null;

    public ?int $legal_entity_type_id = null;

    public ?string $jurisdiction = null;

    public ?string $email = null;

    public ?string $website = null;

    public string $scope_activities_json = '';

    public string $metadata_json = '';

    public function store(): void
    {
        $validated = $this->validate($this->rules());

        $validated['scope_activities'] = $this->decodeJsonField($validated['scope_activities_json']);
        $validated['metadata'] = $this->decodeJsonField($validated['metadata_json']);

        unset($validated['scope_activities_json'], $validated['metadata_json']);

        Company::query()->create($validated);

        Session::flash('success', __('Company created successfully.'));

        $this->redirect(route('admin.companies.index'), navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.companies.create', [
            'parentCompanies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'legalEntityTypes' => LegalEntityType::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'countries' => Country::query()->orderBy('country')->get(['iso', 'country']),
        ]);
    }

    protected function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists(Company::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique(Company::class, 'code')],
            'status' => ['required', 'in:active,suspended,pending,archived'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'legal_entity_type_id' => ['nullable', 'integer', 'exists:company_legal_entity_types,id'],
            'jurisdiction' => ['nullable', 'string', 'max:2', 'exists:geonames_countries,iso'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'scope_activities_json' => ['nullable', 'json'],
            'metadata_json' => ['nullable', 'json'],
        ];
    }
}
