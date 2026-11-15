<?php

namespace Tests\Feature;

use App\Mail\PhpMailTransport;
use App\Models\CommerceNoticeDelivery;
use App\Models\User;
use App\Notifications\CommerceNotice;
use App\Services\CommerceNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class PhpMailTransportTest extends TestCase
{
    use RefreshDatabase;

    private function email(): Email
    {
        return (new Email)->from('sender@example.com')->to('customer@example.com')
            ->subject('Order confirmation — MYR')->text('Plain notice')->html('<p>Order notice</p>');
    }

    public function test_mail_driver_is_registered_without_smtp_configuration(): void
    {
        $this->assertInstanceOf(PhpMailTransport::class, Mail::mailer('mail')->getSymfonyTransport());
    }

    public function test_php_mail_arguments_preserve_mime_and_do_not_duplicate_to_or_subject_headers(): void
    {
        $calls = [];
        $transport = new PhpMailTransport(function (...$arguments) use (&$calls): bool {
            $calls[] = $arguments;

            return true;
        });
        $transport->send($this->email()->cc('copy@example.com')->attach('attachment contents', 'test.txt', 'text/plain'));
        $this->assertCount(1, $calls);
        [$to, $subject, $body, $headers] = $calls[0];
        $this->assertSame('customer@example.com', $to);
        $this->assertStringNotContainsString("\n", $subject);
        $this->assertStringContainsString('Order', $subject);
        $this->assertStringContainsString('Cc: copy@example.com', $headers);
        $this->assertStringContainsString('From: sender@example.com', $headers);
        $this->assertStringContainsString('multipart/mixed', $headers);
        $this->assertStringContainsString('Message-ID:', $headers);
        $this->assertDoesNotMatchRegularExpression('/^(To|Subject|Bcc):/mi', $headers);
        $this->assertStringContainsString('Order notice', $body);
        $this->assertStringContainsString('test.txt', $body);
        $this->assertStringContainsString('Plain notice', $body);
        $this->assertSame('php-mail', (string) $transport);
    }

    public function test_rejected_hand_off_throws_instead_of_reporting_success(): void
    {
        $transport = new PhpMailTransport(fn () => false);
        $this->expectException(TransportException::class);
        $transport->send($this->email());
    }

    public function test_blind_recipients_fail_closed_without_exposure_or_send(): void
    {
        $transport = new PhpMailTransport(function (): bool {
            $this->fail('Unsupported recipients must not be sent.');
        });
        $this->expectException(TransportException::class);
        $transport->send($this->email()->bcc('private@example.com'));
    }

    public function test_custom_envelope_cannot_redirect_mail(): void
    {
        $transport = new PhpMailTransport(function (): bool {
            $this->fail('Custom envelope must not be sent.');
        });
        $this->expectException(TransportException::class);
        $transport->send($this->email(), new Envelope(new Address('sender@example.com'), [new Address('other@example.com')]));
    }

    public function test_raw_mime_is_rejected_before_native_hand_off(): void
    {
        $transport = new PhpMailTransport(function (): bool {
            $this->fail('Raw message must not be sent.');
        });
        $this->expectException(TransportException::class);
        $transport->send(new RawMessage("From: sender@example.com\r\nTo: customer@example.com\r\n\r\nQA"),
            new Envelope(new Address('sender@example.com'), [new Address('customer@example.com')]));
    }

    public function test_failed_native_hand_off_does_not_mark_a_commerce_notice_processed(): void
    {
        Mail::extend('mail', fn () => new PhpMailTransport(fn () => false));
        Mail::purge('mail');
        config(['mail.default' => 'mail']);
        $user = User::factory()->create();
        app(CommerceNotificationService::class)->welcome($user);
        $delivery = CommerceNoticeDelivery::firstOrFail();
        try {
            $user->notifyNow(new CommerceNotice($delivery->id, $delivery->notification_id), ['mail']);
            $this->fail('Rejected hand-off must remain retryable.');
        } catch (TransportException) {
            $this->assertNull($delivery->fresh()->mail_sent_at);
            $this->assertNull($delivery->fresh()->mail_suppressed_at);
        }
        Mail::extend('mail', fn () => new PhpMailTransport(fn () => true));
        Mail::purge('mail');
        $user->notifyNow(new CommerceNotice($delivery->id, $delivery->notification_id), ['mail']);
        $this->assertNotNull($delivery->fresh()->mail_sent_at);
    }
}
