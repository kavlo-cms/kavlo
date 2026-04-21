<?php

namespace App\Services;

use App\Mail\PlainTextMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class KavloMailDelivery
{
    public function queue(string $recipient, Mailable $mail): void
    {
        $this->configure($mail);

        Mail::to($recipient)->queue($mail);
    }

    public function queuePlainText(string $recipient, string $subject, string $body): void
    {
        $this->queue($recipient, new PlainTextMail($subject, $body));
    }

    /**
     * @return array{connection: string, queue: string, async: bool, after_commit: bool, failed_jobs: int|null}
     */
    public function status(): array
    {
        $connection = $this->connectionName();
        $queue = $this->queueName();
        $afterCommit = $this->afterCommit();
        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->count()
            : null;

        return [
            'connection' => $connection,
            'queue' => $queue,
            'async' => $this->isAsync(),
            'after_commit' => $afterCommit,
            'failed_jobs' => $failedJobs,
        ];
    }

    public function isAsync(): bool
    {
        return ! in_array($this->connectionName(), ['sync', 'deferred', 'background', 'null'], true);
    }

    public function afterCommit(): bool
    {
        return (bool) config("queue.connections.{$this->connectionName()}.after_commit", false);
    }

    public function connectionName(): string
    {
        return (string) (config('cms.mail.queue.connection')
            ?: config('queue.default', 'sync'));
    }

    public function queueName(): string
    {
        return (string) config('cms.mail.queue.name', 'mail');
    }

    private function configure(Mailable $mail): void
    {
        $mail->onConnection($this->connectionName())
            ->onQueue($this->queueName());

        if ($this->afterCommit()) {
            $mail->afterCommit();
        }
    }
}
