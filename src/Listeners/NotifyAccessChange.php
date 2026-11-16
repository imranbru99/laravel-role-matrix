<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Optional notification hook. Does nothing unless mail or a Slack webhook is configured.
 */
final class NotifyAccessChange implements ShouldQueue
{
    public function handle(object $event): void
    {
        $summary = $event::class;

        $address = config('role-matrix.events.mail_to');

        if (is_string($address) && $address !== '') {
            Mail::raw($summary, function ($message) use ($address): void {
                $message->to($address)->subject('Role Matrix change');
            });
        }

        $webhook = config('role-matrix.events.slack_webhook');

        if (is_string($webhook) && $webhook !== '') {
            Http::post($webhook, ['text' => $summary]);
        }

        if ((bool) config('role-matrix.events.log', false)) {
            Log::info('role-matrix.event', ['event' => $summary]);
        }
    }
}
