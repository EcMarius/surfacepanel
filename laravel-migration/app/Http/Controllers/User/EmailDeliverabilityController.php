<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DNSZone;
use App\Models\DNSRecord;
use Illuminate\Support\Facades\Auth;

class EmailDeliverabilityController extends Controller
{
    /**
     * Display email deliverability dashboard
     */
    public function index(Request $request): Response
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        // Get all domains for this account
        $domains = $this->getAccountDomains($account);

        // Check deliverability status for each domain
        $domainStatus = [];
        foreach ($domains as $domain) {
            $domainStatus[$domain] = $this->checkDeliverabilityStatus($domain, $account);
        }

        return response()->view('user.email-deliverability.index', [
            'account' => $account,
            'domains' => $domains,
            'domain_status' => $domainStatus,
        ]);
    }

    /**
     * Generate and install DKIM keys for a domain
     */
    public function installDKIM(Request $request): JsonResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validated = $request->validate([
            'domain' => 'required|string',
        ]);

        $domain = $validated['domain'];

        // Check if domain belongs to account
        if (!$this->domainBelongsToAccount($domain, $account)) {
            return response()->json(['error' => 'Domain not found'], 404);
        }

        try {
            // Generate DKIM key pair
            $keyPair = $this->generateDKIMKeys($domain);

            // Store private key securely
            $this->storeDKIMPrivateKey($domain, $keyPair['private_key']);

            // Install DKIM DNS record
            $this->installDKIMDNSRecord($domain, $account, $keyPair['public_key']);

            // Configure mail server with DKIM
            $this->configureDKIMSigning($domain, $keyPair['private_key']);

            return response()->json([
                'success' => true,
                'message' => 'DKIM keys generated and installed successfully',
                'public_key' => $keyPair['public_key'],
                'dns_record' => $this->formatDKIMRecord($keyPair['public_key']),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate and install SPF record
     */
    public function installSPF(Request $request): JsonResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validated = $request->validate([
            'domain' => 'required|string',
            'include_mx' => 'boolean',
            'include_a' => 'boolean',
            'include_ip' => 'boolean',
            'additional_includes' => 'nullable|string',
        ]);

        $domain = $validated['domain'];

        if (!$this->domainBelongsToAccount($domain, $account)) {
            return response()->json(['error' => 'Domain not found'], 404);
        }

        try {
            // Generate SPF record
            $spfRecord = $this->generateSPFRecord(
                $domain,
                $account,
                $validated['include_mx'] ?? true,
                $validated['include_a'] ?? true,
                $validated['include_ip'] ?? true,
                $validated['additional_includes'] ?? ''
            );

            // Install SPF DNS record
            $this->installSPFDNSRecord($domain, $account, $spfRecord);

            return response()->json([
                'success' => true,
                'message' => 'SPF record installed successfully',
                'spf_record' => $spfRecord,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate and install DMARC policy
     */
    public function installDMARC(Request $request): JsonResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validated = $request->validate([
            'domain' => 'required|string',
            'policy' => 'required|in:none,quarantine,reject',
            'subdomain_policy' => 'nullable|in:none,quarantine,reject',
            'percentage' => 'nullable|integer|min:0|max:100',
            'rua' => 'nullable|email',
            'ruf' => 'nullable|email',
        ]);

        $domain = $validated['domain'];

        if (!$this->domainBelongsToAccount($domain, $account)) {
            return response()->json(['error' => 'Domain not found'], 404);
        }

        try {
            // Generate DMARC record
            $dmarcRecord = $this->generateDMARCRecord(
                $validated['policy'],
                $validated['subdomain_policy'] ?? null,
                $validated['percentage'] ?? 100,
                $validated['rua'] ?? null,
                $validated['ruf'] ?? null
            );

            // Install DMARC DNS record
            $this->installDMARCDNSRecord($domain, $account, $dmarcRecord);

            return response()->json([
                'success' => true,
                'message' => 'DMARC policy installed successfully',
                'dmarc_record' => $dmarcRecord,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check email deliverability status for a domain
     */
    public function checkStatus(Request $request, string $domain): JsonResponse
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        if (!$this->domainBelongsToAccount($domain, $account)) {
            return response()->json(['error' => 'Domain not found'], 404);
        }

        $status = $this->checkDeliverabilityStatus($domain, $account);

        return response()->json($status);
    }

    /**
     * Generate DKIM key pair
     */
    private function generateDKIMKeys(string $domain): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Generate key pair
        $privateKey = openssl_pkey_new($config);

        // Extract private key
        openssl_pkey_export($privateKey, $privateKeyString);

        // Extract public key
        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyString = $publicKeyDetails['key'];

        // Format public key for DNS (remove headers and newlines)
        $publicKeyFormatted = str_replace([
            '-----BEGIN PUBLIC KEY-----',
            '-----END PUBLIC KEY-----',
            "\n", "\r"
        ], '', $publicKeyString);

        return [
            'private_key' => $privateKeyString,
            'public_key' => $publicKeyFormatted,
        ];
    }

    /**
     * Store DKIM private key securely
     */
    private function storeDKIMPrivateKey(string $domain, string $privateKey): void
    {
        $keyPath = "/etc/virpanel/dkim/{$domain}.private";

        // Create directory if not exists
        if (!is_dir('/etc/virpanel/dkim')) {
            mkdir('/etc/virpanel/dkim', 0700, true);
        }

        // Write private key
        file_put_contents($keyPath, $privateKey);
        chmod($keyPath, 0600);
    }

    /**
     * Install DKIM DNS record
     */
    private function installDKIMDNSRecord(string $domain, Account $account, string $publicKey): void
    {
        $zone = DNSZone::where('domain', $domain)
            ->where('account_id', $account->id)
            ->first();

        if (!$zone) {
            throw new \Exception('DNS zone not found');
        }

        // Delete existing DKIM record
        DNSRecord::where('zone_id', $zone->id)
            ->where('name', 'default._domainkey')
            ->where('type', 'TXT')
            ->delete();

        // Create DKIM record
        DNSRecord::create([
            'zone_id' => $zone->id,
            'name' => 'default._domainkey',
            'type' => 'TXT',
            'value' => "v=DKIM1; k=rsa; p={$publicKey}",
            'ttl' => 3600,
        ]);

        // Update zone serial
        $this->updateZoneSerial($zone);
    }

    /**
     * Generate SPF record
     */
    private function generateSPFRecord(
        string $domain,
        Account $account,
        bool $includeMX,
        bool $includeA,
        bool $includeIP,
        string $additionalIncludes
    ): string {
        $parts = ['v=spf1'];

        if ($includeMX) {
            $parts[] = 'mx';
        }

        if ($includeA) {
            $parts[] = 'a';
        }

        if ($includeIP) {
            $parts[] = "ip4:{$account->primary_ip}";
        }

        // Add additional includes
        if (!empty($additionalIncludes)) {
            $includes = explode(',', $additionalIncludes);
            foreach ($includes as $include) {
                $include = trim($include);
                if (!empty($include)) {
                    $parts[] = "include:{$include}";
                }
            }
        }

        // Always end with ~all (soft fail) for safety
        $parts[] = '~all';

        return implode(' ', $parts);
    }

    /**
     * Install SPF DNS record
     */
    private function installSPFDNSRecord(string $domain, Account $account, string $spfRecord): void
    {
        $zone = DNSZone::where('domain', $domain)
            ->where('account_id', $account->id)
            ->first();

        if (!$zone) {
            throw new \Exception('DNS zone not found');
        }

        // Delete existing SPF record
        DNSRecord::where('zone_id', $zone->id)
            ->where('name', '@')
            ->where('type', 'TXT')
            ->where('value', 'like', 'v=spf1%')
            ->delete();

        // Create SPF record
        DNSRecord::create([
            'zone_id' => $zone->id,
            'name' => '@',
            'type' => 'TXT',
            'value' => $spfRecord,
            'ttl' => 3600,
        ]);

        $this->updateZoneSerial($zone);
    }

    /**
     * Generate DMARC record
     */
    private function generateDMARCRecord(
        string $policy,
        ?string $subdomainPolicy,
        int $percentage,
        ?string $rua,
        ?string $ruf
    ): string {
        $parts = [
            "v=DMARC1",
            "p={$policy}",
        ];

        if ($subdomainPolicy) {
            $parts[] = "sp={$subdomainPolicy}";
        }

        if ($percentage < 100) {
            $parts[] = "pct={$percentage}";
        }

        if ($rua) {
            $parts[] = "rua=mailto:{$rua}";
        }

        if ($ruf) {
            $parts[] = "ruf=mailto:{$ruf}";
        }

        // Add alignment and reporting
        $parts[] = "adkim=r"; // Relaxed DKIM alignment
        $parts[] = "aspf=r";  // Relaxed SPF alignment

        return implode('; ', $parts);
    }

    /**
     * Install DMARC DNS record
     */
    private function installDMARCDNSRecord(string $domain, Account $account, string $dmarcRecord): void
    {
        $zone = DNSZone::where('domain', $domain)
            ->where('account_id', $account->id)
            ->first();

        if (!$zone) {
            throw new \Exception('DNS zone not found');
        }

        // Delete existing DMARC record
        DNSRecord::where('zone_id', $zone->id)
            ->where('name', '_dmarc')
            ->where('type', 'TXT')
            ->delete();

        // Create DMARC record
        DNSRecord::create([
            'zone_id' => $zone->id,
            'name' => '_dmarc',
            'type' => 'TXT',
            'value' => $dmarcRecord,
            'ttl' => 3600,
        ]);

        $this->updateZoneSerial($zone);
    }

    /**
     * Configure DKIM signing in mail server
     */
    private function configureDKIMSigning(string $domain, string $privateKey): void
    {
        // Configure OpenDKIM or similar
        // This would integrate with Exim/Postfix DKIM configuration

        // For Exim with DKIM support
        $configPath = "/etc/virpanel/dkim/{$domain}.conf";
        $config = [
            "domain={$domain}",
            "selector=default",
            "keyfile=/etc/virpanel/dkim/{$domain}.private",
        ];

        file_put_contents($configPath, implode("\n", $config));
    }

    /**
     * Check deliverability status for a domain
     */
    private function checkDeliverabilityStatus(string $domain, Account $account): array
    {
        $zone = DNSZone::where('domain', $domain)
            ->where('account_id', $account->id)
            ->first();

        $status = [
            'domain' => $domain,
            'dkim' => ['status' => 'not_configured', 'score' => 0],
            'spf' => ['status' => 'not_configured', 'score' => 0],
            'dmarc' => ['status' => 'not_configured', 'score' => 0],
            'overall_score' => 0,
        ];

        if (!$zone) {
            return $status;
        }

        // Check DKIM
        $dkimRecord = DNSRecord::where('zone_id', $zone->id)
            ->where('name', 'default._domainkey')
            ->where('type', 'TXT')
            ->where('value', 'like', 'v=DKIM1%')
            ->first();

        if ($dkimRecord) {
            $status['dkim'] = [
                'status' => 'configured',
                'score' => 35,
                'record' => $dkimRecord->value,
            ];
        }

        // Check SPF
        $spfRecord = DNSRecord::where('zone_id', $zone->id)
            ->where('name', '@')
            ->where('type', 'TXT')
            ->where('value', 'like', 'v=spf1%')
            ->first();

        if ($spfRecord) {
            $status['spf'] = [
                'status' => 'configured',
                'score' => 35,
                'record' => $spfRecord->value,
            ];
        }

        // Check DMARC
        $dmarcRecord = DNSRecord::where('zone_id', $zone->id)
            ->where('name', '_dmarc')
            ->where('type', 'TXT')
            ->where('value', 'like', 'v=DMARC1%')
            ->first();

        if ($dmarcRecord) {
            $status['dmarc'] = [
                'status' => 'configured',
                'score' => 30,
                'record' => $dmarcRecord->value,
            ];
        }

        // Calculate overall score
        $status['overall_score'] = $status['dkim']['score'] +
                                   $status['spf']['score'] +
                                   $status['dmarc']['score'];

        return $status;
    }

    /**
     * Get all domains for an account
     */
    private function getAccountDomains(Account $account): array
    {
        $domains = [$account->domain];

        // Add addon domains
        $addonDomains = $account->addonDomains()->pluck('domain')->toArray();
        $domains = array_merge($domains, $addonDomains);

        return $domains;
    }

    /**
     * Check if domain belongs to account
     */
    private function domainBelongsToAccount(string $domain, Account $account): bool
    {
        $domains = $this->getAccountDomains($account);
        return in_array($domain, $domains);
    }

    /**
     * Format DKIM record for display
     */
    private function formatDKIMRecord(string $publicKey): string
    {
        return "default._domainkey IN TXT \"v=DKIM1; k=rsa; p={$publicKey}\"";
    }

    /**
     * Update DNS zone serial number
     */
    private function updateZoneSerial(DNSZone $zone): void
    {
        $today = date('Ymd');
        $currentSerial = (string)$zone->serial;

        if (substr($currentSerial, 0, 8) === $today) {
            $counter = (int)substr($currentSerial, 8) + 1;
            $newSerial = $today . str_pad($counter, 2, '0', STR_PAD_LEFT);
        } else {
            $newSerial = $today . '01';
        }

        $zone->serial = $newSerial;
        $zone->save();
    }
}
