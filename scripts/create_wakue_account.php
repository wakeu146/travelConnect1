<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Country;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

$user = User::firstOrCreate(
    ['email' => 'wakeuemmanuel146@gmail.com'],
    [
        'name' => 'Wakue Emmanuel',
        'password' => Hash::make('password'),
        'role' => UserRole::AGENCY_OWNER->value,
        'email_verified_at' => now(),
    ]
);

$user->update([
    'name' => 'Wakue Emmanuel',
    'role' => UserRole::AGENCY_OWNER->value,
    'email_verified_at' => now(),
]);

$agency = Agency::where('user_id', $user->id)->firstOrNew();
$agency->fill([
    'user_id' => $user->id,
    'company_name' => 'Wakue Emmanuel Travel',
    'description' => 'Verified travel agency profile for Wakeu Emmanuel.',
    'verification_status' => 'verified',
    'trust_score' => 95,
    'address' => 'Yaoundé, Cameroon',
    'phone' => '+237 690 000 000',
    'email' => 'wakeuemmanuel146@gmail.com',
    'website' => 'https://wakueexample.com',
]);
$agency->save();

$country = Country::firstOrCreate(['name' => 'Cameroon'], ['code' => 'CM']);
$agency->countries()->syncWithoutDetaching([$country->id]);

echo "user_id={$user->id}\n";
echo "user_email={$user->email}\n";
echo 'user_role='.($user->role instanceof UserRole ? $user->role->value : $user->role)."\n";
echo "agency_id={$agency->id}\n";
echo "agency_name={$agency->company_name}\n";
echo 'verification_status='.($agency->verification_status->value ?? $agency->verification_status)."\n";
