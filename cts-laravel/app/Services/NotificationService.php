<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function send(string $accountNumber, string $message, string $channel = 'SMS'): bool
    {
        return match ($channel) {
            'SMS'   => $this->sendSMS($accountNumber, $message),
            'EMAIL' => $this->sendEmail($accountNumber, $message),
            'BOTH'  => $this->sendSMS($accountNumber, $message) && $this->sendEmail($accountNumber, $message),
            default => false,
        };
    }

    public function sendSMS(string $accountNumber, string $message): bool
    {
        $mobile = $this->getMobileFromCBS($accountNumber);
        if (!$mobile) return false;

        try {
            $response = Http::post(config('cts.notifications.sms_gateway'), [
                'to'      => $mobile,
                'message' => $message,
                'sender'  => 'IOBCTS',
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('SMS send failed', ['account' => $accountNumber, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function sendEmail(string $accountNumber, string $message, ?string $attachmentPath = null): bool
    {
        $email = $this->getEmailFromCBS($accountNumber);
        if (!$email) return false;

        try {
            Mail::raw($message, function ($mail) use ($email, $attachmentPath) {
                $mail->to($email)
                     ->from('cts@iob.in', 'Indian Overseas Bank - CTS')
                     ->subject('CTS Notification - Indian Overseas Bank');
                if ($attachmentPath) {
                    $mail->attach($attachmentPath);
                }
            });
            return true;
        } catch (\Exception $e) {
            Log::error('Email send failed', ['account' => $accountNumber, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function notifyITTeam(string $message, string $severity = 'CRITICAL'): void
    {
        $recipients = explode(',', env('IT_TEAM_EMAILS', ''));
        foreach ($recipients as $email) {
            Mail::raw("[{$severity}] {$message}", function ($mail) use ($email) {
                $mail->to(trim($email))
                     ->subject('[CTS Alert] ' . now()->format('d-M-Y H:i'));
            });
        }
    }

    public function emailReturnMemo(string $accountNumber, string $memoPath, array $returnData): bool
    {
        $message = "Dear Customer,\n\nYour cheque no. {$returnData['cheque_number']} "
                 . "dated {$returnData['instrument_date']} for Rs. "
                 . number_format($returnData['amount'], 2)
                 . " has been returned. Reason: {$returnData['return_reason']}\n\n"
                 . "Please contact your branch for further assistance.\n\n"
                 . "Indian Overseas Bank";

        return $this->sendEmail($accountNumber, $message, $memoPath);
    }

    private function getMobileFromCBS(string $accountNumber): ?string
    {
        return cache()->remember("mobile_{$accountNumber}", 300, function () use ($accountNumber) {
            $result = \DB::table('cbs_account_master')
                         ->where('account_number', $accountNumber)
                         ->value('mobile_number');
            return $result;
        });
    }

    private function getEmailFromCBS(string $accountNumber): ?string
    {
        return cache()->remember("email_{$accountNumber}", 300, function () use ($accountNumber) {
            return \DB::table('cbs_account_master')
                      ->where('account_number', $accountNumber)
                      ->value('email_id');
        });
    }
}
