<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTest extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mail:test {email : Address to deliver the test message to}';

    /**
     * @var string
     */
    protected $description = 'Send a test message to verify the configured mail transport';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $this->line('Mailer: <info>'.config('mail.default').'</info>');
        $this->line('From:   <info>'.config('mail.from.address').'</info>');

        try {
            Mail::raw(
                'This is a test message from DexVault. If you received it, mail delivery is working.',
                fn ($message) => $message->to($email)->subject('DexVault mail test'),
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->error('Mail delivery failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Test message sent to '.$email.'.');

        return self::SUCCESS;
    }
}
