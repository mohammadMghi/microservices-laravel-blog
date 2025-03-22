<?php

namespace App\Services\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function handle($name, $email, $password)
    {
        $user = new User();

        $user->name = $name;

        $user->email = $email;

        $user->password = Hash::make($password);

        $user->save();
    }
}