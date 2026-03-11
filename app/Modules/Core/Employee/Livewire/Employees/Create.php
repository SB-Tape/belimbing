<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\Employee\Livewire\Employees;

use App\Base\Foundation\Livewire\Concerns\DecodesJsonFields;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Department;
use App\Modules\Core\Employee\Models\Employee;
use App\Modules\Core\Employee\Models\EmployeeType;
use App\Modules\Core\User\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Create extends Component
{
    use DecodesJsonFields;

    public ?int $company_id = null;

    public ?int $department_id = null;

    public ?int $supervisor_id = null;

    public string $employee_number = '';

    public string $full_name = '';

    public ?string $short_name = null;

    public ?string $designation = null;

    public string $employee_type = 'full_time';

    public string $status = 'active';

    public ?string $email = null;

    public ?string $mobile_number = null;

    public ?string $employment_start = null;

    public ?string $employment_end = null;

    public ?string $job_description = null;

    public ?int $user_id = null;

    public string $metadata_json = '';

    public function store(): void
    {
        $validated = $this->validate($this->rules());

        $validated['metadata'] = $this->decodeJsonField($validated['metadata_json'] ?? null);
        $validated['job_description'] = $validated['job_description'] ?? null;

        unset($validated['metadata_json']);

        if (($validated['employee_type'] ?? '') === 'digital_worker') {
            $validated['user_id'] = null;
        }

        Employee::query()->create($validated);

        Session::flash('success', __('Employee created successfully.'));

        $this->redirect(route('admin.employees.index'), navigate: true);
    }

    protected function rules(): array
    {
        $rules = [
            'company_id' => ['required', 'integer', Rule::exists(Company::class, 'id')],
            'department_id' => ['nullable', 'integer', 'exists:company_departments,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => [
                $this->employee_type === 'digital_worker' ? 'required' : 'nullable',
                'integer',
                Rule::exists(Employee::class, 'id'),
            ],
            'employee_number' => ['required', 'string', 'max:255', Rule::unique('employees')->where('company_id', $this->company_id)],
            'full_name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'employee_type' => ['required', Rule::exists(EmployeeType::class, 'code')],
            'job_description' => ['nullable', 'string', 'max:65535'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:pending,probation,active,inactive,terminated'],
            'employment_start' => ['nullable', 'date'],
            'employment_end' => ['nullable', 'date'],
            'metadata_json' => ['nullable', 'json'],
        ];

        return $rules;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.employees.create', [
            'companies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'departments' => Department::query()
                ->with('type')
                ->orderBy('department_type_id')
                ->get(['id', 'company_id', 'department_type_id']),
            'supervisors' => Employee::query()
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'company_id']),
            'employeeTypes' => EmployeeType::query()->global()->orderBy('code')->get(['id', 'code', 'label', 'is_system']),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
