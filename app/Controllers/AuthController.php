<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login', ['title' => 'Login'], 'guest');
    }

    public function login(): void
    {
        $this->validateCsrf();
        $email = $this->input('email');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            flash('error', 'Email and password are required.');
            $this->redirect('/login');
        }

        if (!Auth::attempt($email, $password)) {
            flash('error', 'Invalid credentials or inactive account.');
            store_old(['email' => $email]);
            $this->redirect('/login');
        }

        clear_old();
        flash('success', 'Welcome back!');
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        flash('success', 'You have been logged out.');
        $this->redirect('/login');
    }
}
