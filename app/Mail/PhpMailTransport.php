<?php

namespace App\Mail;

use Closure;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * PHP mail() hand-off only: server php.ini controls the delivery agent.
 * Application notices use To recipients; Bcc/custom envelopes fail closed.
 */
class PhpMailTransport extends AbstractTransport
{
    /** @param Closure(string, string, string, string): bool|null $sender */
    public function __construct(private ?Closure $sender = null)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (! $original instanceof Message) {
            throw new TransportException('PHP mail transport requires a structured MIME message.');
        }
        $email = MessageConverter::toEmail($original);
        $recipients = array_map(fn ($address) => $address->getAddress(), [...$email->getTo(), ...$email->getCc()]);
        $envelopeRecipients = array_map(fn ($address) => $address->getAddress(), $message->getEnvelope()->getRecipients());
        sort($recipients);
        sort($envelopeRecipients);
        if ($email->getBcc() || ! $email->getTo() || $recipients !== $envelopeRecipients) {
            throw new TransportException('PHP mail transport requires To recipients and does not support Bcc or custom envelopes.');
        }
        [$headers, $body] = explode("\r\n\r\n", $email->toString(), 2);
        $headers = preg_replace('/^(?:To|Subject|Bcc):[^\r\n]*(?:\r\n[ \t][^\r\n]*)*(?:\r\n|$)/mi', '', $headers);
        $prepared = $email->getPreparedHeaders();
        $to = $this->unfold($prepared->get('To')?->getBodyAsString() ?? '');
        $subject = $this->unfold($prepared->get('Subject')?->getBodyAsString() ?? '');
        if ($this->sender !== null) {
            $accepted = ($this->sender)($to, $subject, $body, trim($headers));
        } else {
            if (! function_exists('mail')) {
                throw new TransportException('PHP mail() is unavailable on this server.');
            }
            // Do not expose php.ini paths or recipient data in warnings/job logs.
            set_error_handler(static function (): never {
                throw new TransportException('PHP mail() could not hand off the message. Check server mail configuration.');
            });
            try {
                // No shell parameters and no application SMTP credentials.
                $accepted = mail($to, $subject, $body, trim($headers));
            } finally {
                restore_error_handler();
            }
        }
        if (! $accepted) {
            throw new TransportException('PHP mail() rejected the message hand-off.');
        }
    }

    private function unfold(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    public function __toString(): string
    {
        return 'php-mail';
    }
}
