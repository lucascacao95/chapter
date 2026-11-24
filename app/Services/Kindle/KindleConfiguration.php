<?php

namespace App\Services\Kindle;

use Illuminate\Support\Facades\Validator;

class KindleConfiguration
{
    public function ready(): bool
    {
        $smtp = config('mail.mailers.kindle');
        $recipient = config('kindle.recipient');

        return is_array($smtp) && $smtp['transport'] === 'smtp' && $smtp['host'] === 'smtp.gmail.com'
            && filled($smtp['password'] ?? null)
            && Validator::make(['email' => $smtp['username'] ?? null], ['email' => 'required|email'])->passes()
            && is_string($recipient) && preg_match('/^[^@\s]+@kindle\.com$/i', $recipient) === 1;
    }
}
