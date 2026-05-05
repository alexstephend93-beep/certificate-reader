<?php

namespace App\Http\Controllers;

use App\Models\AdminDashboard;
use App\Models\AdminCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;

class AdminCredentialController extends Controller
{
    public function index()
    {
        $dashboards = AdminDashboard::with('credentials')
            ->orderBy('is_favorite', 'desc')
            ->orderBy('name')
            ->get();
        
        $favorites = $dashboards->where('is_favorite', true);
        $recent = $dashboards->whereNotNull('last_used')->sortByDesc('last_used')->take(5);
        
        return view('admin-credentials.index', compact('dashboards', 'favorites', 'recent'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'emails' => 'required|array|min:1',
            'emails.*' => 'required|email',
            'passwords' => 'required|array|min:1',
            'passwords.*' => 'required|string|min:4',
        ], [
            'name.required' => 'Dashboard name is required',
            'url.required' => 'URL is required',
            'url.url' => 'Please enter a valid URL',
            'emails.required' => 'At least one email is required',
            'emails.*.required' => 'Email is required',
            'emails.*.email' => 'Please enter a valid email address',
            'passwords.required' => 'At least one password is required',
            'passwords.*.required' => 'Password is required',
            'passwords.*.min' => 'Password must be at least 4 characters',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dashboard = AdminDashboard::create([
            'name' => $request->name,
            'integration_name' => $request->integration_name,
            'url' => $request->url,
            'icon' => $request->icon ?? 'box',
            'color' => $request->color,
            'description' => $request->description,
        ]);

        foreach ($request->emails as $key => $email) {
            if (!empty($email) && !empty($request->passwords[$key])) {
                $dashboard->credentials()->create([
                    'email' => $email,
                    'username' => $request->usernames[$key] ?? null,
                    'password' => $request->passwords[$key],
                    'role' => $request->roles[$key] ?? 'User',
                    'is_default' => $key == 0,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard added successfully!',
            'redirect' => route('admin-credentials.index')
        ]);
    }

    public function update(Request $request, $id)
    {
        $dashboard = AdminDashboard::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
        ], [
            'name.required' => 'Dashboard name is required',
            'url.required' => 'URL is required',
            'url.url' => 'Please enter a valid URL',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dashboard->update([
            'name' => $request->name,
            'integration_name' => $request->integration_name,
            'url' => $request->url,
            'icon' => $request->icon ?? 'box',
            'color' => $request->color,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard updated successfully!',
            'redirect' => route('admin-credentials.index')
        ]);
    }

    public function addCredential(Request $request, $dashboardId)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:4',
        ], [
            'email.required' => 'Email is required',
            'email.email' => 'Please enter a valid email address',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 4 characters',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dashboard = AdminDashboard::findOrFail($dashboardId);
        
        $dashboard->credentials()->create([
            'email' => $request->email,
            'username' => $request->username,
            'password' => $request->password,
            'role' => $request->role ?? 'User',
            'is_default' => $dashboard->credentials()->count() == 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Credential added successfully!'
        ]);
    }

    public function updateCredential(Request $request, $credentialId)
    {
        $credential = AdminCredential::findOrFail($credentialId);
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ], [
            'email.required' => 'Email is required',
            'email.email' => 'Please enter a valid email address',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'email' => $request->email,
            'username' => $request->username,
            'role' => $request->role,
        ];
        
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }
        
        $credential->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Credential updated successfully!'
        ]);
    }

    public function edit($id)
    {
        $dashboard = AdminDashboard::with('credentials')->findOrFail($id);
        return response()->json($dashboard);
    }

    public function getCredential($id)
    {
        $credential = AdminCredential::findOrFail($id);
        return response()->json([
            'email' => $credential->email,
            'username' => $credential->username,
            'role' => $credential->role,
        ]);
    }

    public function destroy($id)
    {
        $dashboard = AdminDashboard::findOrFail($id);
        $dashboard->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard deleted successfully!'
        ]);
    }

    public function deleteCredential($credentialId)
    {
        $credential = AdminCredential::findOrFail($credentialId);
        $credential->delete();

        return response()->json([
            'success' => true,
            'message' => 'Credential deleted successfully!'
        ]);
    }

    public function setDefaultCredential($credentialId)
    {
        $credential = AdminCredential::findOrFail($credentialId);
        
        AdminCredential::where('dashboard_id', $credential->dashboard_id)
            ->update(['is_default' => false]);
        
        $credential->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default credential set successfully!'
        ]);
    }

    public function toggleFavorite($id)
    {
        $dashboard = AdminDashboard::findOrFail($id);
        $dashboard->update(['is_favorite' => !$dashboard->is_favorite]);

        return response()->json([
            'success' => true, 
            'is_favorite' => $dashboard->is_favorite
        ]);
    }

    public function autoLogin($credentialId)
    {
        $credential = AdminCredential::with('dashboard')->findOrFail($credentialId);
        $credential->incrementUsage();
        $credential->dashboard->incrementUsage();
        
        return view('admin-credentials.auto-login', compact('credential'));
    }

    public function copyCredentials($credentialId)
    {
        $credential = AdminCredential::findOrFail($credentialId);
        
        return response()->json([
            'success' => true,
            'email' => $credential->email,
            'username' => $credential->username,
            'password' => $credential->password,
            'role' => $credential->role,
        ]);
    }
}