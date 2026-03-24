<?php

use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Employee\Models\Employee;
use App\Modules\Core\User\Database\Seeders\Dev\DevUserSeeder;
use App\Modules\Core\User\Models\User;

const BLB_DEV_USER_SEEDER_TEST_ADMIN_EMAIL = 'licensee-admin@example.com';
const BLB_DEV_USER_SEEDER_TEST_OTHER_EMAIL = 'licensee-user@example.com';

function invokeAssignAdminEmployeeId(): void
{
    putenv('ADMIN_EMAIL='.BLB_DEV_USER_SEEDER_TEST_ADMIN_EMAIL);
    $_ENV['ADMIN_EMAIL'] = BLB_DEV_USER_SEEDER_TEST_ADMIN_EMAIL;
    $_SERVER['ADMIN_EMAIL'] = BLB_DEV_USER_SEEDER_TEST_ADMIN_EMAIL;

    $seeder = new DevUserSeeder;
    $method = new ReflectionMethod($seeder, 'assignAdminEmployeeId');
    $method->setAccessible(true);
    $method->invoke($seeder);
}

afterEach(function (): void {
    putenv('ADMIN_EMAIL');
    unset($_ENV['ADMIN_EMAIL'], $_SERVER['ADMIN_EMAIL']);
});

test('dev user seeder links the configured admin user instead of the first licensee user', function (): void {
    Company::provisionLicensee();

    $otherUser = User::factory()->create([
        'company_id' => Company::LICENSEE_ID,
        'email' => BLB_DEV_USER_SEEDER_TEST_OTHER_EMAIL,
        'employee_id' => null,
    ]);

    $adminUser = User::factory()->create([
        'company_id' => Company::LICENSEE_ID,
        'email' => BLB_DEV_USER_SEEDER_TEST_ADMIN_EMAIL,
        'employee_id' => null,
    ]);

    $adminEmployee = Employee::factory()->create([
        'company_id' => Company::LICENSEE_ID,
        'email' => BLB_DEV_USER_SEEDER_TEST_ADMIN_EMAIL,
    ]);

    invokeAssignAdminEmployeeId();

    expect($adminUser->fresh()->employee_id)->toBe($adminEmployee->id)
        ->and($otherUser->fresh()->employee_id)->toBeNull();
});
