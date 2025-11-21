<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EcommerceClient;
use App\Models\Client;            // <- remove if you don't want to link POS clients
use App\Models\StoreSetting;      // <- only for passing $s to views
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use DB;

class StoreAuthController extends Controller
{
    /**
     * Only allow same-origin path redirects.
     */
    protected function safeRedirect(Request $request): string
    {
        // 1) explicit redirect param (path only)
        $redir = (string) $request->input('redirect', '');
        if ($redir && Str::startsWith($redir, ['/']) && !Str::startsWith($redir, ['//'])) {
            return $redir;
        }

        // 2) intended URL (from auth middleware)
        if ($intended = (string) session('url.intended')) {
            if (Str::startsWith($intended, ['/']) && !Str::startsWith($intended, ['//'])) {
                return $intended;
            }
        }

        // 3) fallback → checkout
        return route('checkout');
    }

    /** GET /store/login */
    public function showLogin(Request $request)
    {
        $s = StoreSetting::first();
        $redirect = $this->safeRedirect($request);
        return view('store.auth.login', compact('s', 'redirect'));
    }

    /** POST /store/login */
    public function login(Request $request)
    {
        $cred = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
            'remember' => ['nullable','boolean'],
            'redirect' => ['nullable','string'],
        ]);

        // Require active customer
        $attempt = [
            'email'    => $cred['email'],
            'password' => $cred['password'],
            'status'   => 1,
        ];

        if (Auth::guard('store')->attempt($attempt, (bool)($cred['remember'] ?? false))) {
            $request->session()->regenerate();
            return redirect()->to($this->safeRedirect($request));
        }

        return back()
            ->withErrors(['email' => __('Invalid credentials')])
            ->onlyInput('email');
    }

    /** GET /store/register */
    public function showRegister(Request $request)
    {
        $s = StoreSetting::first();
        $redirect = $this->safeRedirect($request);
        return view('store.auth.register', compact('s', 'redirect'));
    }

    /** POST /store/register */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => ['required','string','max:120'],
            'email'                 => ['required','email','max:190','unique:ecommerce_clients,email'],
            'password'              => ['required','confirmed','min:6'],
        ]);

        $user = DB::transaction(function () use ($data) {
            // Create or reuse a Client row, assigning a new sequential code if creating
            $client = Client::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'code' => $this->getNumberOrder(),
                    // add any other required Client columns here with sensible defaults
                ]
            );

            // Create the ecommerce user linked to that client
            return EcommerceClient::create([
                'client_id' => $client->id,
                'username'  => Str::slug(Str::before($data['email'], '@')),
                'email'     => $data['email'],
                'status'    => 1,
                'password'  => Hash::make($data['password']), // hash explicitly
            ]);
        });

        Auth::guard('store')->login($user);
        $request->session()->regenerate();

        return redirect()->to($this->safeRedirect($request));
    }

    /** POST /store/logout */
   public function logout(Request $request)
    {
        Auth::guard('store')->logout();

        // Only flush the store guard, not the entire session
        $request->session()->forget('guard_store'); // optional, depends on your guard key
        $request->session()->regenerateToken();

        return redirect()->route('store.login.show');
    }
    
    public function getNumberOrder()
    {
        $last = DB::table('clients')->latest('id')->first();

        if ($last) {
            $code = $last->code + 1;
        } else {
            $code = 1;
        }
        return $code;
    }


}
