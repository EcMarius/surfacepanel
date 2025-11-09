<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class CronController extends Controller
{
    private Connection $db;
    private TemplateEngine $template;
    private string $prefix;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->db = $app->get('database');
        $this->template = new TemplateEngine($app);
        $this->prefix = config('database.prefix', 'vp_');
    }

    /**
     * Display cron jobs overview
     */
    public function index(Request $request): Response
    {
        $accountId = $this->getUserAccountId();

        if (!$accountId) {
            $_SESSION['error'] = 'No account found';
            return new RedirectResponse('/user/dashboard');
        }

        // Get account details
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        // Get all cron jobs
        $cronJobs = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}cron_jobs WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        // Common cron job templates
        $templates = [
            [
                'name' => 'Every Minute',
                'schedule' => '* * * * *',
                'description' => 'Runs every minute',
            ],
            [
                'name' => 'Every 5 Minutes',
                'schedule' => '*/5 * * * *',
                'description' => 'Runs every 5 minutes',
            ],
            [
                'name' => 'Every Hour',
                'schedule' => '0 * * * *',
                'description' => 'Runs at the start of every hour',
            ],
            [
                'name' => 'Daily at Midnight',
                'schedule' => '0 0 * * *',
                'description' => 'Runs once a day at midnight',
            ],
            [
                'name' => 'Daily at 2 AM',
                'schedule' => '0 2 * * *',
                'description' => 'Runs once a day at 2:00 AM',
            ],
            [
                'name' => 'Weekly on Sunday',
                'schedule' => '0 0 * * 0',
                'description' => 'Runs every Sunday at midnight',
            ],
            [
                'name' => 'Monthly on 1st',
                'schedule' => '0 0 1 * *',
                'description' => 'Runs on the 1st of every month at midnight',
            ],
            [
                'name' => 'Every 15 Minutes',
                'schedule' => '*/15 * * * *',
                'description' => 'Runs every 15 minutes',
            ],
        ];

        return new Response($this->template->render('user/cron/index.html.twig', [
            'cron_jobs' => $cronJobs,
            'account' => $account,
            'templates' => $templates,
        ]));
    }

    /**
     * Create new cron job
     */
    public function store(Request $request): Response
    {
        $accountId = $this->getUserAccountId();

        $minute = trim($request->request->get('minute', '*'));
        $hour = trim($request->request->get('hour', '*'));
        $day = trim($request->request->get('day', '*'));
        $month = trim($request->request->get('month', '*'));
        $weekday = trim($request->request->get('weekday', '*'));
        $command = trim($request->request->get('command'));
        $email = trim($request->request->get('email', ''));

        // Validation
        if (!$command) {
            $_SESSION['error'] = 'Command is required';
            return new RedirectResponse('/user/cron');
        }

        // Validate cron syntax
        if (!$this->validateCronSyntax($minute, $hour, $day, $month, $weekday)) {
            $_SESSION['error'] = 'Invalid cron syntax';
            return new RedirectResponse('/user/cron');
        }

        // Build cron schedule
        $schedule = "{$minute} {$hour} {$day} {$month} {$weekday}";

        // Get account details for username
        $account = $this->db->fetchAssociative(
            "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        try {
            $this->db->beginTransaction();

            // Create cron job
            $this->db->insert($this->prefix . 'cron_jobs', [
                'account_id' => $accountId,
                'schedule' => $schedule,
                'command' => $command,
                'email' => $email ?: null,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $cronId = $this->db->lastInsertId();

            // Update system crontab
            $this->updateSystemCrontab($account['username']);

            $this->db->commit();

            logger("Cron job created: {$schedule} - {$command}");

            $_SESSION['success'] = 'Cron job created successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create cron job: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/cron');
    }

    /**
     * Update cron job
     */
    public function update(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        $minute = trim($request->request->get('minute', '*'));
        $hour = trim($request->request->get('hour', '*'));
        $day = trim($request->request->get('day', '*'));
        $month = trim($request->request->get('month', '*'));
        $weekday = trim($request->request->get('weekday', '*'));
        $command = trim($request->request->get('command'));
        $email = trim($request->request->get('email', ''));

        // Verify cron job belongs to user
        $cronJob = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}cron_jobs WHERE id = ? AND account_id = ?",
            [$id, $accountId]
        );

        if (!$cronJob) {
            $_SESSION['error'] = 'Cron job not found';
            return new RedirectResponse('/user/cron');
        }

        // Validation
        if (!$command) {
            $_SESSION['error'] = 'Command is required';
            return new RedirectResponse('/user/cron');
        }

        // Validate cron syntax
        if (!$this->validateCronSyntax($minute, $hour, $day, $month, $weekday)) {
            $_SESSION['error'] = 'Invalid cron syntax';
            return new RedirectResponse('/user/cron');
        }

        // Build cron schedule
        $schedule = "{$minute} {$hour} {$day} {$month} {$weekday}";

        // Get account details for username
        $account = $this->db->fetchAssociative(
            "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        try {
            $this->db->beginTransaction();

            // Update cron job
            $this->db->update($this->prefix . 'cron_jobs', [
                'schedule' => $schedule,
                'command' => $command,
                'email' => $email ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            // Update system crontab
            $this->updateSystemCrontab($account['username']);

            $this->db->commit();

            logger("Cron job updated: ID {$id}");

            $_SESSION['success'] = 'Cron job updated successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to update cron job: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/cron');
    }

    /**
     * Toggle cron job status
     */
    public function toggleStatus(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        // Verify cron job belongs to user
        $cronJob = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}cron_jobs WHERE id = ? AND account_id = ?",
            [$id, $accountId]
        );

        if (!$cronJob) {
            $_SESSION['error'] = 'Cron job not found';
            return new RedirectResponse('/user/cron');
        }

        // Get account details for username
        $account = $this->db->fetchAssociative(
            "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        try {
            $this->db->beginTransaction();

            // Toggle status
            $newStatus = $cronJob['status'] === 'active' ? 'inactive' : 'active';

            $this->db->update($this->prefix . 'cron_jobs', [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            // Update system crontab
            $this->updateSystemCrontab($account['username']);

            $this->db->commit();

            logger("Cron job status changed: ID {$id} - {$newStatus}");

            $_SESSION['success'] = "Cron job {$newStatus}";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to toggle cron job status: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/cron');
    }

    /**
     * Delete cron job
     */
    public function delete(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $this->db->beginTransaction();

            // Verify cron job belongs to user
            $cronJob = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}cron_jobs WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$cronJob) {
                $_SESSION['error'] = 'Cron job not found';
                return new RedirectResponse('/user/cron');
            }

            // Get account details for username
            $account = $this->db->fetchAssociative(
                "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
                [$accountId]
            );

            // Delete cron job
            $this->db->delete($this->prefix . 'cron_jobs', ['id' => $id]);

            // Update system crontab
            $this->updateSystemCrontab($account['username']);

            $this->db->commit();

            logger("Cron job deleted: ID {$id}");

            $_SESSION['success'] = 'Cron job deleted successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to delete cron job: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/cron');
    }

    /**
     * View cron job execution logs
     */
    public function logs(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        // Verify cron job belongs to user
        $cronJob = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}cron_jobs WHERE id = ? AND account_id = ?",
            [$id, $accountId]
        );

        if (!$cronJob) {
            $_SESSION['error'] = 'Cron job not found';
            return new RedirectResponse('/user/cron');
        }

        // Get execution logs
        $logs = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}cron_logs WHERE cron_job_id = ? ORDER BY executed_at DESC LIMIT 50",
            [$id]
        );

        return new Response($this->template->render('user/cron/logs.html.twig', [
            'cron_job' => $cronJob,
            'logs' => $logs,
        ]));
    }

    /**
     * Get user's account ID
     */
    private function getUserAccountId(): ?int
    {
        $user = Auth::user();

        $account = $this->db->fetchAssociative(
            "SELECT id FROM {$this->prefix}accounts WHERE user_id = ? LIMIT 1",
            [$user->getId()]
        );

        return $account ? (int)$account['id'] : null;
    }

    /**
     * Validate cron syntax
     */
    private function validateCronSyntax(string $minute, string $hour, string $day, string $month, string $weekday): bool
    {
        $fields = [$minute, $hour, $day, $month, $weekday];

        foreach ($fields as $field) {
            // Allow *, numbers, ranges (1-5), steps (*/5), lists (1,2,3)
            if (!preg_match('/^(\*|(\*\/[0-9]+)|([0-9,\-\/]+))$/', $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update system crontab for user
     */
    private function updateSystemCrontab(string $username): void
    {
        // Get all active cron jobs for this account
        $account = $this->db->fetchAssociative(
            "SELECT id FROM {$this->prefix}accounts WHERE username = ?",
            [$username]
        );

        $cronJobs = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}cron_jobs WHERE account_id = ? AND status = 'active'",
            [$account['id']]
        );

        // Build crontab content
        $crontab = "# VirPanel Cron Jobs for {$username}\n";
        $crontab .= "# DO NOT EDIT MANUALLY - Managed by VirPanel\n\n";

        foreach ($cronJobs as $job) {
            if ($job['email']) {
                $crontab .= "MAILTO={$job['email']}\n";
            }
            $crontab .= "{$job['schedule']} {$job['command']}\n";
        }

        // Write crontab file
        $cronFile = "/var/spool/cron/crontabs/{$username}";
        $cronDir = dirname($cronFile);

        if (!is_dir($cronDir)) {
            mkdir($cronDir, 0755, true);
        }

        file_put_contents($cronFile, $crontab);
        chmod($cronFile, 0600);

        // In production: reload cron service
        // exec('systemctl reload cron');

        logger("Crontab updated for user: {$username}");
    }
}
