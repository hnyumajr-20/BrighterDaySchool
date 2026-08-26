<?php

namespace App\Mail\Transport;

use MessageBird\Bird;
use MessageBird\Wire\Model\EmailAttachment;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

class BirdTransport extends AbstractTransport
{
    public function __construct(protected Bird $bird)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $email->getFrom();
        $to = $email->getTo();

        $html = $email->getHtmlBody() ?? nl2br(e((string) $email->getTextBody()));

        $attachments = array_map(
            fn (DataPart $part) => (new EmailAttachment())
                ->setFilename($part->getFilename() ?? 'attachment')
                ->setContentType($part->getContentType())
                ->setContent(base64_encode($part->getBody())),
            $email->getAttachments(),
        );

        $this->bird->email->send(
            from: $from ? $this->formatAddress($from[0]) : null,
            to: array_map(fn (Address $address) => $address->getAddress(), $to),
            subject: (string) $email->getSubject(),
            html: $html,
            text: $email->getTextBody(),
            attachments: $attachments ?: null,
        );
    }

    private function formatAddress(Address $address): string
    {
        if ($address->getName() === '') {
            return $address->getAddress();
        }

        $escapedName = addcslashes($address->getName(), '"\\');

        return sprintf('"%s" <%s>', $escapedName, $address->getAddress());
    }

    public function __toString(): string
    {
        return 'bird';
    }
}
