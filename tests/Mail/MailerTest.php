<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Mail;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\Config\Typed\MailConfig;
use YasserElgammal\Green\ErrorHandling\GreenErrorKernel;
use YasserElgammal\Green\Mail\Mailer;
use YasserElgammal\Green\Mail\MailerInterface;
use YasserElgammal\Green\Mail\PendingMail;
use YasserElgammal\Green\Mail\Transports\ArrayTransport;

final class MailerTest extends TestCase
{
    private ?Application $app = null;

    protected function setUp(): void
    {
        parent::setUp();
        $viewsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'views';

        $this->app = new Application(
            configOverrides: [
                'view' => ['path' => $viewsPath],
                'mail' => [
                    'host' => '127.0.0.1',
                    'port' => 1025,
                    'from' => [
                        'address' => 'sender@example.com',
                        'name' => 'Default Sender',
                    ],
                ],
            ],
            basePath: dirname(__DIR__, 2),
        );
    }

    protected function tearDown(): void
    {
        $this->app?->make(GreenErrorKernel::class)->unregister();
        parent::tearDown();
    }

    public function test_mailer_resolves_from_container_and_helper(): void
    {
        $mailer = $this->app->make(Mailer::class);
        self::assertInstanceOf(Mailer::class, $mailer);
        self::assertSame($mailer, $this->app->make(MailerInterface::class));
        self::assertSame($mailer, mailer());
    }

    public function test_mailer_helper_with_recipient_returns_pending_mail(): void
    {
        $pending = mailer('test@example.com', 'Test User');
        self::assertInstanceOf(PendingMail::class, $pending);

        $email = $pending->build();
        $recipients = $email->getTo();
        self::assertCount(1, $recipients);
        self::assertSame('test@example.com', $recipients[0]->getAddress());
        self::assertSame('Test User', $recipients[0]->getName());
    }

    public function test_fake_captures_sent_messages_with_array_transport(): void
    {
        /** @var Mailer $mailer */
        $mailer = $this->app->make(Mailer::class);
        $fake = $mailer->fake();

        self::assertInstanceOf(ArrayTransport::class, $fake);
        self::assertCount(0, $fake);

        mailer('recipient@example.com', 'Recipient')
            ->subject('Test Subject')
            ->html('<p>Hello</p>')
            ->send();

        self::assertCount(1, $fake);
        self::assertTrue($fake->hasSentTo('recipient@example.com'));

        $emails = $fake->emails();
        self::assertCount(1, $emails);
        self::assertSame('Test Subject', $emails[0]->getSubject());
        self::assertStringContainsString('<p>Hello</p>', $emails[0]->getHtmlBody());
        self::assertSame('sender@example.com', $emails[0]->getFrom()[0]->getAddress());
        self::assertSame('Default Sender', $emails[0]->getFrom()[0]->getName());
    }

    public function test_pending_mail_renders_twig_template_via_view(): void
    {
        /** @var Mailer $mailer */
        $mailer = $this->app->make(Mailer::class);
        $fake = $mailer->fake();

        mailer('user@example.com')
            ->subject('Welcome!')
            ->view('lazy', ['name' => 'Green Framework'])
            ->send();

        self::assertCount(1, $fake);
        $email = $fake->emails()[0];
        self::assertSame('Welcome!', $email->getSubject());
        self::assertStringContainsString('Hello Green Framework', $email->getHtmlBody());
    }

    public function test_pending_mail_supports_text_alternative_and_raw_html(): void
    {
        /** @var Mailer $mailer */
        $mailer = $this->app->make(Mailer::class);
        $fake = $mailer->fake();

        mailer('user@example.com')
            ->subject('Alert')
            ->html('<h1>Alert</h1>')
            ->text('Plain Alert')
            ->send();

        $email = $fake->emails()[0];
        self::assertStringContainsString('<h1>Alert</h1>', $email->getHtmlBody());
        self::assertSame('Plain Alert', $email->getTextBody());
    }

    public function test_pending_mail_supports_full_fluent_options_and_attachments(): void
    {
        /** @var Mailer $mailer */
        $mailer = $this->app->make(Mailer::class);
        $fake = $mailer->fake();

        mailer()
            ->from('custom@example.com', 'Custom Sender')
            ->to('primary@example.com', 'Primary')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Report')
            ->html('Report content')
            ->attachData('CSV,DATA,123', 'report.csv', 'text/csv')
            ->with(function (Email $email) {
                $email->getHeaders()->addTextHeader('X-Custom-Header', 'Green');
            })
            ->send();

        self::assertCount(1, $fake);
        $email = $fake->emails()[0];

        self::assertSame('custom@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('Custom Sender', $email->getFrom()[0]->getName());
        self::assertSame('primary@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('cc@example.com', $email->getCc()[0]->getAddress());
        self::assertSame('bcc@example.com', $email->getBcc()[0]->getAddress());
        self::assertSame('reply@example.com', $email->getReplyTo()[0]->getAddress());
        self::assertSame('Green', $email->getHeaders()->get('X-Custom-Header')?->getBodyAsString());
        self::assertCount(1, $email->getAttachments());
    }

    public function test_pending_mail_normalizes_various_address_formats(): void
    {
        $pending = mailer()->to([
            'first@example.com' => 'First User',
            'second@example.com',
            new Address('third@example.com', 'Third User'),
        ]);

        $email = $pending->build();
        $to = $email->getTo();

        self::assertCount(3, $to);
        self::assertSame('first@example.com', $to[0]->getAddress());
        self::assertSame('First User', $to[0]->getName());
        self::assertSame('second@example.com', $to[1]->getAddress());
        self::assertSame('third@example.com', $to[2]->getAddress());
        self::assertSame('Third User', $to[2]->getName());
    }

    public function test_mailer_raw_sends_plain_text(): void
    {
        /** @var Mailer $mailer */
        $mailer = $this->app->make(Mailer::class);
        $fake = $mailer->fake();

        $mailer->raw('Raw message content', function (Email $email) {
            $email->to('raw@example.com')->subject('Raw Subject');
        });

        self::assertCount(1, $fake);
        $email = $fake->emails()[0];
        self::assertSame('Raw Subject', $email->getSubject());
        self::assertSame('Raw message content', $email->getTextBody());
        self::assertSame('raw@example.com', $email->getTo()[0]->getAddress());
    }

    public function test_mailer_set_instance_deprecated_helper(): void
    {
        $config = new MailConfig('127.0.0.1', 1025, null, null, 'manual@example.com', 'Manual');
        $customMailer = new Mailer($config, new ArrayTransport());

        mailer_set_instance($customMailer);
        self::assertSame($customMailer, mailer());
    }
}
