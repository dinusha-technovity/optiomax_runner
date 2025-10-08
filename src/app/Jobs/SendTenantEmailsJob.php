<?php

namespace App\Jobs;

use App\Models\RegistrationDebug;
use App\Models\User;
use App\Models\tenants;
use App\Mail\TenantMails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class SendTenantEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $registrationDebugId;
    public $timeout = 60;
    public $tries = 3;

    public function __construct($registrationDebugId)
    {
        $this->registrationDebugId = (int) $registrationDebugId; // Ensure it's serializable
        $this->onQueue('emails'); // Set default queue
    }

    public function handle()
    {
        Log::info("SendTenantEmailsJob started for registration ID: {$this->registrationDebugId}");
        
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if (!$reg) {
            Log::error("Registration record not found for SendTenantEmailsJob: {$this->registrationDebugId}");
            throw new \Exception('Registration record not found');
        }

        Log::info("Starting SendTenantEmailsJob for registration ID: {$reg->id}");

        $userDetails = Cache::get("tenant_users_{$reg->id}");
        if (!$userDetails) {
            Log::warning("User details not found in cache for registration ID: {$reg->id}. Completing without emails.");
            $reg->update(['status' => 'completed', 'error_message' => 'Email skipped - user details not found in cache']);
            return;
        }

        $tenantUser = User::find($reg->owner_user_id);
        if (!$tenantUser) {
            Log::error("Tenant user not found for registration ID: {$reg->id}");
            throw new \Exception('Tenant user not found');
        }

        Log::info("Sending tenant emails for registration ID: {$reg->id}");

        try {
            $emailsSent = 0;

            // Send emails for invited users
            if (isset($userDetails['invited_users']) && is_array($userDetails['invited_users'])) {
                foreach ($userDetails['invited_users'] as $user) {
                    Log::info("Processing user for emails: " . json_encode($user));
                    
                    // Send app invitation emails
                    if (isset($user['is_app_user']) && $user['is_app_user']) {
                        $this->sendAppInvitationEmail($user, $user['password'], $tenantUser->name);
                        $emailsSent++;
                    }

                    // Send portal invitation emails for owners
                    if (isset($user['is_owner']) && $user['is_owner'] && isset($user['portal_password'])) {
                        $this->sendPortalInvitationEmail($user, $user['portal_password'], $tenantUser->name);
                        $emailsSent++;
                    }
                }
            }

            // Clean up cache after successful email sending
            Cache::forget("tenant_users_{$reg->id}");

            // Update registration status to emails_sent (not completed yet)
            $reg->update([
                'status' => 'emails_sent',
                'error_message' => null
            ]);

            Log::info("Tenant emails sent for ID: {$reg->id}. Sent {$emailsSent} emails. Moving to payment processing...");

        } catch (\Exception $e) {
            Log::error("Failed to send tenant emails for registration ID: {$reg->id} - " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }

        Log::info("SendTenantEmailsJob completed successfully for registration ID: {$this->registrationDebugId}");
    }

    private function sendAppInvitationEmail($user, $password, $inviterName)
    {
        try {
            $applicationUrl = env('APPLICATION_URL');
            $userName = $user['name'];
            $email = $user['email'];

            Log::info("Sending app invitation email to: {$email}");

            $data = json_encode([
                'name' => $userName, 
                'email' => $email, 
                'password' => $password, 
                'inviter' => $inviterName
            ]);
            $hashedData = base64_encode($data);
            $signupUrl = $applicationUrl . "/continune_first_sign_up?token={$hashedData}";

            // Send invitation email
            Mail::to($email)->send(new TenantMails(
                $userName, 
                $inviterName, 
                null, 
                null, 
                $signupUrl, 
                $signupUrl, 
                null, 
                "USER_INVITATION"
            ));

            // Send password email
            Mail::to($email)->send(new TenantMails(
                $userName, 
                null, 
                null, 
                $password, 
                null, 
                $signupUrl, 
                null, 
                "USER_INVITATION_PASSWORD"
            ));

            Log::info("App invitation emails sent successfully to: {$email}");

        } catch (\Exception $e) {
            Log::error("Failed to send app invitation to {$user['email']}: " . $e->getMessage());
            // Don't throw exception to continue with other emails
        }
    }

    private function sendPortalInvitationEmail($user, $password, $inviterName)
    {
        try {
            $applicationUrl = env('PORTAL_URL');
            $userName = $user['name'];
            $email = $user['email'];

            Log::info("Sending portal invitation email to: {$email}");

            $data = json_encode([
                'name' => $userName, 
                'email' => $email, 
                'password' => $password, 
                'inviter' => $inviterName
            ]);
            $hashedData = base64_encode($data);
            $signupUrl = $applicationUrl . "/continune_first_sign_up?token={$hashedData}";

            // Send portal invitation email
            Mail::to($email)->send(new \App\Mail\PortalMails(
                $userName, 
                $inviterName, 
                null, 
                null, 
                $signupUrl, 
                $signupUrl, 
                null, 
                "PORTAL_USER_INVITATION"
            ));

            // Send portal password email
            Mail::to($email)->send(new \App\Mail\PortalMails(
                $userName, 
                null, 
                null, 
                $password, 
                null, 
                $signupUrl, 
                null, 
                "PORTAL_USER_INVITATION_PASSWORD"
            ));

            Log::info("Portal invitation emails sent successfully to: {$email}");

        } catch (\Exception $e) {
            Log::error("Failed to send portal invitation to {$user['email']}: " . $e->getMessage());
            // Don't throw exception to continue with other emails
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("SendTenantEmailsJob failed permanently for ID: {$this->registrationDebugId} - " . $exception->getMessage());
        Log::error("Stack trace: " . $exception->getTraceAsString());
        
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if ($reg) {
            $reg->update(['status' => 'email_failed', 'error_message' => $exception->getMessage()]);
        }
        
        // Clean up cache on failure
        Cache::forget("tenant_users_{$this->registrationDebugId}");
    }
}
