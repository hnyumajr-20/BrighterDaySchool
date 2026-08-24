<?php

namespace App\Mail\Transport;

use MessageBird\Bird;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

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

        $this->bird->email->send(
            from: $from ? $this->formatAddress($from[0]) : null,
            to: array_map(fn (Address $address) => $address->getAddress(), $to),
            subject: (string) $email->getSubject(),
            html: $html,
            text: $email->getTextBody(),
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
