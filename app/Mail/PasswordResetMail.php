<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $url;
    public string $messageBody;

    public function __construct(string $name, string $url, string $messageBody)
    {
        $this->name = $name;
        $this->url = $url;
        $this->messageBody = $messageBody;
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Recuperación de contraseña')
            ->view('emails.password_reset')
            ->with([
                'name' => $this->name,
                'url' => $this->url,
                'messageBody' => $this->messageBody,
            ]);
    }
}
