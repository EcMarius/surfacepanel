<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\EmailFilter;
use App\Models\EmailFilterCondition;
use App\Models\EmailFilterAction;
use App\Services\SieveScriptGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmailFilterController extends Controller
{
    protected SieveScriptGenerator $sieveGenerator;

    public function __construct(SieveScriptGenerator $sieveGenerator)
    {
        $this->sieveGenerator = $sieveGenerator;
    }

    /**
     * Display list of email filters
     */
    public function index(Request $request): Response
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        // Get email parameter or use default
        $email = $request->get('email', null);

        // Get all email accounts for this account
        $emailAccounts = $account->emailAccounts()->pluck('email')->toArray();

        // Add account main email if not already in list
        if (!in_array($account->username . '@' . $account->domain, $emailAccounts)) {
            $emailAccounts[] = $account->username . '@' . $account->domain;
        }

        // Get filters, optionally filtered by email
        $filtersQuery = EmailFilter::where('account_id', $account->id)
            ->with(['conditions', 'actions'])
            ->byPriority();

        if ($email) {
            $filtersQuery->forEmail($email);
        }

        $filters = $filtersQuery->get();

        return response()->view('user.email-filters.index', [
            'account' => $account,
            'filters' => $filters,
            'emailAccounts' => $emailAccounts,
            'selectedEmail' => $email,
        ]);
    }

    /**
     * Show form for creating a new filter
     */
    public function create(Request $request): Response
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $email = $request->get('email', null);
        $emailAccounts = $account->emailAccounts()->pluck('email')->toArray();

        if (!in_array($account->username . '@' . $account->domain, $emailAccounts)) {
            $emailAccounts[] = $account->username . '@' . $account->domain;
        }

        return response()->view('user.email-filters.create', [
            'account' => $account,
            'emailAccounts' => $emailAccounts,
            'selectedEmail' => $email,
            'availableFields' => EmailFilterCondition::getAvailableFields(),
            'availableOperators' => EmailFilterCondition::getAvailableOperators(),
            'availableActions' => EmailFilterAction::getAvailableActionTypes(),
        ]);
    }

    /**
     * Store a new email filter
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|integer|min:1|max:999',
            'is_active' => 'boolean',
            'match_type' => 'required|in:all,any',
            'stop_processing' => 'boolean',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required|string',
            'conditions.*.header_name' => 'nullable|string',
            'conditions.*.case_sensitive' => 'boolean',
            'actions' => 'required|array|min:1',
            'actions.*.action_type' => 'required|string',
            'actions.*.action_value' => 'nullable|string',
            'actions.*.vacation_subject' => 'nullable|string',
            'actions.*.vacation_message' => 'nullable|string',
            'actions.*.vacation_days' => 'nullable|integer|min:1|max:30',
        ]);

        try {
            DB::beginTransaction();

            // Create the filter
            $filter = EmailFilter::create([
                'account_id' => $account->id,
                'email' => $validated['email'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'] ?? 100,
                'is_active' => $validated['is_active'] ?? true,
                'match_type' => $validated['match_type'],
                'stop_processing' => $validated['stop_processing'] ?? false,
            ]);

            // Create conditions
            foreach ($validated['conditions'] as $conditionData) {
                EmailFilterCondition::create([
                    'filter_id' => $filter->id,
                    'field' => $conditionData['field'],
                    'operator' => $conditionData['operator'],
                    'value' => $conditionData['value'],
                    'header_name' => $conditionData['header_name'] ?? null,
                    'case_sensitive' => $conditionData['case_sensitive'] ?? false,
                ]);
            }

            // Create actions
            foreach ($validated['actions'] as $index => $actionData) {
                EmailFilterAction::create([
                    'filter_id' => $filter->id,
                    'action_type' => $actionData['action_type'],
                    'action_value' => $actionData['action_value'] ?? null,
                    'vacation_subject' => $actionData['vacation_subject'] ?? null,
                    'vacation_message' => $actionData['vacation_message'] ?? null,
                    'vacation_days' => $actionData['vacation_days'] ?? 7,
                    'order' => $index + 1,
                ]);
            }

            // Generate and save Sieve script
            $this->generateSieveScript($account, $validated['email']);

            DB::commit();

            return redirect()
                ->route('user.email-filters.index', ['email' => $validated['email']])
                ->with('success', 'Email filter created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create filter: ' . $e->getMessage());
        }
    }

    /**
     * Show form for editing a filter
     */
    public function edit(int $id): Response
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $filter = EmailFilter::where('account_id', $account->id)
            ->with(['conditions', 'actions'])
            ->findOrFail($id);

        $emailAccounts = $account->emailAccounts()->pluck('email')->toArray();

        if (!in_array($account->username . '@' . $account->domain, $emailAccounts)) {
            $emailAccounts[] = $account->username . '@' . $account->domain;
        }

        return response()->view('user.email-filters.edit', [
            'account' => $account,
            'filter' => $filter,
            'emailAccounts' => $emailAccounts,
            'availableFields' => EmailFilterCondition::getAvailableFields(),
            'availableOperators' => EmailFilterCondition::getAvailableOperators(),
            'availableActions' => EmailFilterAction::getAvailableActionTypes(),
        ]);
    }

    /**
     * Update an existing filter
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $filter = EmailFilter::where('account_id', $account->id)->findOrFail($id);

        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|integer|min:1|max:999',
            'is_active' => 'boolean',
            'match_type' => 'required|in:all,any',
            'stop_processing' => 'boolean',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required|string',
            'conditions.*.header_name' => 'nullable|string',
            'conditions.*.case_sensitive' => 'boolean',
            'actions' => 'required|array|min:1',
            'actions.*.action_type' => 'required|string',
            'actions.*.action_value' => 'nullable|string',
            'actions.*.vacation_subject' => 'nullable|string',
            'actions.*.vacation_message' => 'nullable|string',
            'actions.*.vacation_days' => 'nullable|integer|min:1|max:30',
        ]);

        try {
            DB::beginTransaction();

            // Update the filter
            $filter->update([
                'email' => $validated['email'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'] ?? 100,
                'is_active' => $validated['is_active'] ?? true,
                'match_type' => $validated['match_type'],
                'stop_processing' => $validated['stop_processing'] ?? false,
            ]);

            // Delete old conditions and actions
            $filter->conditions()->delete();
            $filter->actions()->delete();

            // Create new conditions
            foreach ($validated['conditions'] as $conditionData) {
                EmailFilterCondition::create([
                    'filter_id' => $filter->id,
                    'field' => $conditionData['field'],
                    'operator' => $conditionData['operator'],
                    'value' => $conditionData['value'],
                    'header_name' => $conditionData['header_name'] ?? null,
                    'case_sensitive' => $conditionData['case_sensitive'] ?? false,
                ]);
            }

            // Create new actions
            foreach ($validated['actions'] as $index => $actionData) {
                EmailFilterAction::create([
                    'filter_id' => $filter->id,
                    'action_type' => $actionData['action_type'],
                    'action_value' => $actionData['action_value'] ?? null,
                    'vacation_subject' => $actionData['vacation_subject'] ?? null,
                    'vacation_message' => $actionData['vacation_message'] ?? null,
                    'vacation_days' => $actionData['vacation_days'] ?? 7,
                    'order' => $index + 1,
                ]);
            }

            // Regenerate Sieve script
            $this->generateSieveScript($account, $validated['email']);

            DB::commit();

            return redirect()
                ->route('user.email-filters.index', ['email' => $validated['email']])
                ->with('success', 'Email filter updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update filter: ' . $e->getMessage());
        }
    }

    /**
     * Delete a filter
     */
    public function destroy(int $id): RedirectResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $filter = EmailFilter::where('account_id', $account->id)->findOrFail($id);
        $email = $filter->email;

        try {
            $filter->delete();

            // Regenerate Sieve script
            $this->generateSieveScript($account, $email);

            return redirect()
                ->route('user.email-filters.index', ['email' => $email])
                ->with('success', 'Email filter deleted successfully');

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete filter: ' . $e->getMessage());
        }
    }

    /**
     * Toggle filter active status
     */
    public function toggle(int $id): JsonResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $filter = EmailFilter::where('account_id', $account->id)->findOrFail($id);
        $filter->is_active = !$filter->is_active;
        $filter->save();

        // Regenerate Sieve script
        $this->generateSieveScript($account, $filter->email);

        return response()->json([
            'success' => true,
            'is_active' => $filter->is_active,
            'message' => $filter->is_active ? 'Filter enabled' : 'Filter disabled',
        ]);
    }

    /**
     * Duplicate a filter
     */
    public function duplicate(int $id): RedirectResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $filter = EmailFilter::where('account_id', $account->id)
            ->with(['conditions', 'actions'])
            ->findOrFail($id);

        try {
            $newFilter = $filter->duplicate($filter->name . ' (Copy)');

            return redirect()
                ->route('user.email-filters.edit', $newFilter->id)
                ->with('success', 'Filter duplicated successfully');

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to duplicate filter: ' . $e->getMessage());
        }
    }

    /**
     * Update filter priorities (reorder)
     */
    public function reorder(Request $request): JsonResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validated = $request->validate([
            'filters' => 'required|array',
            'filters.*.id' => 'required|integer',
            'filters.*.priority' => 'required|integer',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['filters'] as $filterData) {
                $filter = EmailFilter::where('account_id', $account->id)
                    ->findOrFail($filterData['id']);
                $filter->priority = $filterData['priority'];
                $filter->save();
            }

            DB::commit();

            // Regenerate Sieve script for all affected emails
            $emails = EmailFilter::where('account_id', $account->id)
                ->whereIn('id', collect($validated['filters'])->pluck('id'))
                ->pluck('email')
                ->unique();

            foreach ($emails as $email) {
                $this->generateSieveScript($account, $email);
            }

            return response()->json([
                'success' => true,
                'message' => 'Filter priorities updated successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update priorities: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test a filter against sample email data
     */
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter_id' => 'nullable|integer',
            'match_type' => 'required|in:all,any',
            'conditions' => 'required|array|min:1',
            'test_data' => 'required|array',
            'test_data.from' => 'required|email',
            'test_data.to' => 'required|email',
            'test_data.subject' => 'required|string',
            'test_data.body' => 'nullable|string',
            'test_data.size' => 'nullable|integer',
        ]);

        try {
            $matchType = $validated['match_type'];
            $testData = $validated['test_data'];
            $results = [];
            $overallMatch = $matchType === 'all';

            foreach ($validated['conditions'] as $conditionData) {
                $condition = new EmailFilterCondition($conditionData);
                $matches = $condition->matches($testData);

                $results[] = [
                    'description' => $condition->getDescription(),
                    'matches' => $matches,
                ];

                if ($matchType === 'all' && !$matches) {
                    $overallMatch = false;
                } elseif ($matchType === 'any' && $matches) {
                    $overallMatch = true;
                }
            }

            if ($matchType === 'any' && !collect($results)->contains('matches', true)) {
                $overallMatch = false;
            }

            return response()->json([
                'success' => true,
                'matches' => $overallMatch,
                'results' => $results,
                'message' => $overallMatch
                    ? 'Filter conditions match the test data'
                    : 'Filter conditions do not match the test data',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export filters as Sieve script
     */
    public function export(Request $request): Response
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $email = $request->get('email');

        if (!$email) {
            abort(400, 'Email parameter is required');
        }

        $script = $this->sieveGenerator->generate($account, $email);

        $filename = str_replace('@', '_at_', $email) . '.sieve';

        return response($script, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Import filters from Sieve script
     */
    public function import(Request $request): JsonResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validated = $request->validate([
            'email' => 'required|email',
            'sieve_script' => 'required|string',
            'replace_existing' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // Delete existing filters if replace_existing is true
            if ($validated['replace_existing'] ?? false) {
                EmailFilter::where('account_id', $account->id)
                    ->where('email', $validated['email'])
                    ->delete();
            }

            // Parse and import Sieve script
            $imported = $this->sieveGenerator->import(
                $account,
                $validated['email'],
                $validated['sieve_script']
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$imported} filter(s)",
                'count' => $imported,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show vacation responder setup
     */
    public function vacation(Request $request): Response
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $email = $request->get('email');
        $emailAccounts = $account->emailAccounts()->pluck('email')->toArray();

        // Check if vacation filter exists
        $vacationFilter = null;
        if ($email) {
            $vacationFilter = EmailFilter::where('account_id', $account->id)
                ->where('email', $email)
                ->whereHas('actions', function ($query) {
                    $query->where('action_type', 'vacation');
                })
                ->with(['actions'])
                ->first();
        }

        return response()->view('user.email-filters.vacation', [
            'account' => $account,
            'emailAccounts' => $emailAccounts,
            'selectedEmail' => $email,
            'vacationFilter' => $vacationFilter,
        ]);
    }

    /**
     * Setup or update vacation responder
     */
    public function setupVacation(Request $request): RedirectResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validated = $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'days_between_responses' => 'nullable|integer|min:1|max:30',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // Check if vacation filter exists
            $filter = EmailFilter::where('account_id', $account->id)
                ->where('email', $validated['email'])
                ->whereHas('actions', function ($query) {
                    $query->where('action_type', 'vacation');
                })
                ->first();

            if ($filter) {
                // Update existing vacation filter
                $filter->update([
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                // Update vacation action
                $action = $filter->actions()->where('action_type', 'vacation')->first();
                $action->update([
                    'vacation_subject' => $validated['subject'],
                    'vacation_message' => $validated['message'],
                    'vacation_days' => $validated['days_between_responses'] ?? 7,
                ]);
            } else {
                // Create new vacation filter
                $filter = EmailFilter::create([
                    'account_id' => $account->id,
                    'email' => $validated['email'],
                    'name' => 'Vacation Auto-Reply',
                    'description' => 'Automatic vacation response',
                    'priority' => 1, // High priority
                    'is_active' => $validated['is_active'] ?? true,
                    'match_type' => 'all',
                    'stop_processing' => false,
                ]);

                // No conditions needed for vacation (applies to all incoming mail)

                // Create vacation action
                EmailFilterAction::create([
                    'filter_id' => $filter->id,
                    'action_type' => 'vacation',
                    'vacation_subject' => $validated['subject'],
                    'vacation_message' => $validated['message'],
                    'vacation_days' => $validated['days_between_responses'] ?? 7,
                    'order' => 1,
                ]);
            }

            // Generate Sieve script
            $this->generateSieveScript($account, $validated['email']);

            DB::commit();

            return redirect()
                ->route('user.email-filters.vacation', ['email' => $validated['email']])
                ->with('success', 'Vacation responder configured successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to setup vacation responder: ' . $e->getMessage());
        }
    }

    /**
     * Disable vacation responder
     */
    public function disableVacation(Request $request): RedirectResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $email = $request->get('email');

        if (!$email) {
            return redirect()
                ->back()
                ->with('error', 'Email parameter is required');
        }

        try {
            $filter = EmailFilter::where('account_id', $account->id)
                ->where('email', $email)
                ->whereHas('actions', function ($query) {
                    $query->where('action_type', 'vacation');
                })
                ->first();

            if ($filter) {
                $filter->update(['is_active' => false]);
                $this->generateSieveScript($account, $email);
            }

            return redirect()
                ->route('user.email-filters.vacation', ['email' => $email])
                ->with('success', 'Vacation responder disabled');

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to disable vacation responder: ' . $e->getMessage());
        }
    }

    /**
     * Generate Sieve script for an email address
     */
    private function generateSieveScript(Account $account, string $email): void
    {
        $script = $this->sieveGenerator->generate($account, $email);
        $this->sieveGenerator->save($account, $email, $script);
    }
}
