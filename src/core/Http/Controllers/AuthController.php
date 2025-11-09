<?php

namespace VirPanel\Core\Http\Controllers;

use VirPanel\Core\Auth\Auth;
use VirPanel\Core\Template\TemplateEngine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Authentication Controller
 *
 * Handles login, logout, and registration
 */
class AuthController
{
    /**
     * Template engine
     *
     * @var TemplateEngine
     */
    protected TemplateEngine $template;

    /**
     * Create a new controller instance
     */
    public function __construct()
    {
        $this->template = app('template');
    }

    /**
     * Show login form
     *
     * @param Request $request
     * @return Response
     */
    public function showLogin(Request $request): Response
    {
        $content = $this->template->render('auth/login.html.twig', [
            'error' => $_SESSION['error'] ?? null,
            'success' => $_SESSION['success'] ?? null,
        ]);

        // Clear flash messages
        unset($_SESSION['error'], $_SESSION['success']);

        return new Response($content);
    }

    /**
     * Handle login attempt
     *
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response
    {
        $username = $request->request->get('username');
        $password = $request->request->get('password');
        $remember = $request->request->get('remember', false);

        // Validate input
        if (empty($username) || empty($password)) {
            $_SESSION['error'] = 'Please enter both username and password.';
            return new RedirectResponse('/login');
        }

        // Attempt authentication
        if (Auth::attempt($username, $password, $remember)) {
            // Get intended URL or default to dashboard
            $intendedUrl = $_SESSION['intended_url'] ?? null;
            unset($_SESSION['intended_url']);

            if ($intendedUrl) {
                return new RedirectResponse($intendedUrl);
            }

            // Redirect based on role
            $user = Auth::user();

            if ($user->isRoot() || $user->getRole() === 'admin') {
                return new RedirectResponse('/admin/dashboard');
            } elseif ($user->isReseller()) {
                return new RedirectResponse('/reseller/dashboard');
            } else {
                return new RedirectResponse('/user/dashboard');
            }
        }

        // Authentication failed
        $_SESSION['error'] = 'Invalid username or password.';
        return new RedirectResponse('/login');
    }

    /**
     * Handle logout
     *
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request): Response
    {
        Auth::logout();

        $_SESSION['success'] = 'You have been logged out successfully.';
        return new RedirectResponse('/login');
    }

    /**
     * Show password reset form
     *
     * @param Request $request
     * @return Response
     */
    public function showPasswordReset(Request $request): Response
    {
        $content = $this->template->render('auth/password-reset.html.twig', [
            'error' => $_SESSION['error'] ?? null,
            'success' => $_SESSION['success'] ?? null,
        ]);

        unset($_SESSION['error'], $_SESSION['success']);

        return new Response($content);
    }

    /**
     * Handle password reset request
     *
     * @param Request $request
     * @return Response
     */
    public function sendPasswordResetLink(Request $request): Response
    {
        $email = $request->request->get('email');

        if (empty($email)) {
            $_SESSION['error'] = 'Please enter your email address.';
            return new RedirectResponse('/password/reset');
        }

        // TODO: Implement password reset email logic
        // For now, just show success message

        $_SESSION['success'] = 'If an account exists with that email, you will receive a password reset link shortly.';
        return new RedirectResponse('/password/reset');
    }

    /**
     * Show password change form
     *
     * @param Request $request
     * @return Response
     */
    public function showPasswordChange(Request $request): Response
    {
        if (!Auth::check()) {
            return new RedirectResponse('/login');
        }

        $content = $this->template->render('auth/password-change.html.twig', [
            'error' => $_SESSION['error'] ?? null,
            'success' => $_SESSION['success'] ?? null,
        ]);

        unset($_SESSION['error'], $_SESSION['success']);

        return new Response($content);
    }

    /**
     * Handle password change
     *
     * @param Request $request
     * @return Response
     */
    public function changePassword(Request $request): Response
    {
        if (!Auth::check()) {
            return new RedirectResponse('/login');
        }

        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        // Validate input
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['error'] = 'All fields are required.';
            return new RedirectResponse('/password/change');
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'New passwords do not match.';
            return new RedirectResponse('/password/change');
        }

        if (strlen($newPassword) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters long.';
            return new RedirectResponse('/password/change');
        }

        $user = Auth::user();

        // Verify current password
        if (!$user->verifyPassword($currentPassword)) {
            $_SESSION['error'] = 'Current password is incorrect.';
            return new RedirectResponse('/password/change');
        }

        // Update password
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $db->update($prefix . 'users', [
            'password' => password_hash($newPassword, PASSWORD_ARGON2ID),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $user->getId()]);

        logger("Password changed for user: {$user->getUsername()}");

        $_SESSION['success'] = 'Password changed successfully.';
        return new RedirectResponse('/password/change');
    }
}
