<?php

namespace Modules\Administrator\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Administrator\Http\Request\UserRequest;
use Modules\Administrator\Services\UserService;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = request()->length ?? 10;
            $start = request()->start ?? 0;
            $search = request()->search['value'] ?? '';
            
            $data = $this->userService->getDataTableData($length, $start, $search);
            
            return response()->json($data);
        }
        return view('administrator::user.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = $this->userService->getFormData();
        return view('administrator::user.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRequest $request)
    {

        try {
            $userData = $request->all();
            $this->userService->createUser($userData);

            return redirect()->route('user.index')
                ->with('success', __('User Created Successfully'));
        } catch (ValidationException $e) {
            return redirect()->route('user.create')
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            report($e);

            return redirect()->route('user.create')
                ->with('error', __('Something went wrong while creating the user. Please try again.'))
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = $this->userService->getFormData($id);
        return view('administrator::user.create', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, string $id)
    {
        try {
            $user = $this->userService->getRepository()->findByEncryptedId($id);
            if (!$user) {
                return redirect()->route('user.index')
                    ->with('error', __('User not found'));
            }
        } catch (\Exception $e) {
            return redirect()->route('user.index')
                ->with('error', __('Invalid user ID'));
        }

        try {
            $userData = $request->all();
            $this->userService->updateUser($id, $userData);

            return redirect()->route('user.index')
                ->with('success', __('User Updated Successfully'));
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()
                ->with('error', __('Something went wrong while updating the user. Please try again.'))
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->userService->deleteUser($id);
            return redirect()->route('user.index')
                ->with('success', __('User Deleted Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('user.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for updating the authenticated user's profile.
     */
    public function updateProfile()
    {
        $user = Auth::user();
        return view('administrator::user.update-profile', compact('user'));
    }

    /**
     * Update the authenticated user's profile.
     * Uses plain Request so validation runs only for the submitted update_type (profile, password, or security).
     */
    public function updateProfileStore(Request $request)
    {
        $user = Auth::user();
        $updateType = $request->input('update_type', 'profile');

        try {
            if ($updateType === 'password') {
                $request->validate([
                    'current_password' => ['required', 'string'],
                    'new_password' => ['required', 'string', 'min:6', 'max:32', 'confirmed'],
                    'new_password_confirmation' => ['required', 'string', 'min:6'],
                ]);

                if (!\Hash::check($request->current_password, $user->password)) {
                    return redirect()->route('user.update-profile')
                        ->with('error', __('Current password is incorrect'));
                }

                $this->userService->updateProfilePassword($user->id, $request->new_password);

                return redirect()->route('user.update-profile')
                    ->with('success', __('Password Updated Successfully'));
            }

            if ($updateType === 'security') {
                $request->validate([
                    'security_question' => ['required', 'string', 'max:255'],
                    'security_answer' => ['required', 'string', 'max:255'],
                ]);

                $this->userService->updateProfileSecurity($user->id, $request->all());

                return redirect()->route('user.update-profile')
                    ->with('success', __('Security Settings Updated Successfully'));
            }

            // Profile update: validate only profile fields
            $companyId = session('company.company_id');
            $request->validate([
                'name' => ['required', 'string', 'max:55'],
                'email' => [
                    'required',
                    'string',
                    'max:55',
                    'email',
                    Rule::unique('users', 'email')
                        ->ignore($user->id)
                        ->where('company_id', $companyId),
                ],
                'phone' => ['required', 'string', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:10', 'max:20'],
                'photo' => ['nullable', 'string'],
            ]);

            $userData = $request->only(['name', 'email', 'phone', 'photo']);
            $this->userService->updateProfile($user->id, $userData);

            // fetch updated user
            $user = $this->userService->getRepository()->find($user->id);
            if (!$user) {
                return redirect()->route('user.update-profile')
                    ->with('error', __('User not found'));
            }

            // after update profile update session
            session()->put('user', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'photo' => $user->photo ? asset('public/uploads/' . $user->photo) : asset('public/uploads/dummy_images/default-picture.png'),
                'role' => $user->roles->first()->name,
            ]);

            return redirect()->route('user.update-profile')
                ->with('success', __('Profile Updated Successfully'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('user.update-profile')
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('user.update-profile')
                ->with('error', $e->getMessage());
        }
    }
}

