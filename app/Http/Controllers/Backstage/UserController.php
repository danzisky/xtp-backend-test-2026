<?php

namespace App\Http\Controllers\Backstage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backstage\Users\StoreRequest;
use App\Http\Requests\Backstage\Users\UpdateRequest;
use App\Mail\Backstage\Users\WelcomeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('backstage.users.index');
    }

    public function create(): View
    {
        return view('backstage.users.create', [
            'user' => new User,
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $password = Str::random(10);
        $data['password'] = bcrypt($password);

        try {
            $user = DB::transaction(function () use ($data) {
                $user = User::create($data);
                $user->update([
                    'ott' => encrypt($user->id),
                ]);

                Mail::to($user)->queue(new WelcomeMail($user));

                Log::info('Backstage user created', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return $user;
            });
        } catch (\Throwable $exception) {
            Log::error('Backstage user creation failed', [
                'email' => $data['email'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'The user could not be created.');

            return redirect()->back()->withInput();
        }

        session()->flash('success', 'The user has been created!');

        return redirect()->route('backstage.users.index');
    }

    public function edit(User $user): View
    {
        return view('backstage.users.edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use (&$data, $user) {
                if (isset($data['password'])) {
                    if (auth()->id() !== $user->id) {
                        unset($data['password']);
                    } else {
                        $data['password'] = bcrypt($data['password']);
                    }
                }

                $user->update($data);

                Log::info('Backstage user updated', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::error('Backstage user update failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'The user details could not be saved.');

            return redirect()->back()->withInput();
        }

        session()->flash('success', 'The user details have been saved!');

        return redirect()->route('backstage.users.edit', $user->id);
    }

    public function destroy(User $user): RedirectResponse
    {
        $userId = $user->id;
        $email = $user->email;

        try {
            DB::transaction(function () use ($user) {
                $user->forceDelete();
            });

            Log::info('Backstage user deleted', [
                'user_id' => $userId,
                'email' => $email,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Backstage user deletion failed', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'The user could not be removed.');

            return redirect()->back();
        }

        session()->flash('success', 'The user has been removed!');

        return redirect()->route('backstage.users.index');
    }
}
