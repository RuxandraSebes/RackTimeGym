<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreGymAccountRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class GymAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::where('gym_id', $request->user()->gym_id)->get()
        );
    }

    public function store(StoreGymAccountRequest $request): UserResource
    {
        $role = Role::from($request->string('role')->toString());

        Gate::authorize('create', [User::class, $role]);

        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
            'role' => $role,
            'gym_id' => $request->user()->gym_id,
        ]);

        return new UserResource($user);
    }
}
