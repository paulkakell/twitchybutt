<?php

namespace App\Logging;

use Monolog\LogRecord;

final class RedactLogRecord
{
    private const EVENTS = [
        'cms.member.registered', 'cms.admin.created', 'cms.post.created',
        'cms.post.updated', 'cms.report.received', 'cms.invoice.quoted', 'cms.exception',
        'cms.mail.failed', 'cms.mail.enqueue_failed', 'cms.mail.queued', 'cms.mail.processed',
        'cms.email.verified', 'cms.password.reset',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        // Deny by default: arbitrary messages, nested context and extra data are private.
        $message = in_array($record->message, self::EVENTS, true) ? $record->message : 'cms.log.redacted';
        $context = [];
        foreach (['actor_id', 'post_id', 'report_id'] as $key) {
            $value = $record->context[$key] ?? null;
            if (is_int($value) && $value > 0) {
                $context[$key] = $value;
            }
        }
        foreach (['request_id', 'invoice_id'] as $key) {
            $value = $record->context[$key] ?? null;
            if (is_string($value) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) === 1) {
                $context[$key] = $value;
            }
        }
        if ($message === 'cms.exception') {
            // Class names can include paths for anonymous exceptions. Do not emit them.
            $context['exception_type'] = 'Throwable';
        }

        return $record->with(message: $message, channel: 'cms', context: $context, extra: []);
    }
}
