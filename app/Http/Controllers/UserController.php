<?php

namespace App\Http\Controllers;

use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{

    public function me(Request $request): UserResource
    {
        return $request
            ->user()
            ->toResource(UserResource::class);
    }
}
