<?php

namespace Genhoi\SymfonyNotifier;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\TransportException as HttpTransportException;

final class SmscTransport extends AbstractTransport
{
    protected const HOST = 'smsc.ru';

    private $login;
    private $password;
    private $sender;

    public function __construct(string $login, string $password, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->login = $login;
        $this->password = $password;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('smsc://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $endpoint = sprintf('https://%s/sys/send.php', $this->getEndpoint());
        $body = [
            'login' => $this->login,
            'psw' => $this->password,
            'phones' => $message->getPhone(),
            'mes' => $message->getSubject(),
            'fmt' => 3, // 3 - json format response
            'cost' => 0, // 0 - no cost info in response
        ];
        if (null !== $this->sender) {
            $body['sender'] = $this->sender;
        }

        $response = $this->client->request('POST', $endpoint, [
            'body' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (HttpTransportException $exception) {
            throw new TransportException(sprintf('Unable to send the SMS: "%s".', $exception->getMessage()), $response);
        }
        if (200 !== $statusCode) {
            $errorMessage = sprintf(
                'Unable to send the SMS. responseCode: "%s", responseContent: "%s"',
                $statusCode,
                $response->getContent(false)
            );
            throw new TransportException($errorMessage, $response);
        }

        $responseBody = $response->toArray(false);
        $error = $responseBody['error'] ?? null;
        $errorCode = $responseBody['error_code'] ?? null;
        if ($errorCode === 6) {
            throw new MessageDeniedException($error, $response);
        }
        if (null !== $error) {
            throw new TransportException(sprintf('Unable to send the SMS: "%s".', $error), $response);
        }

        $id = $responseBody['id'] ?? '';
        $sent = new SentMessage($message, (string) $this);
        $sent->setMessageId($id);

        return $sent;
    }

    public function setSender(?string $sender): SmscTransport
    {
        $this->sender = $sender;

        return $this;
    }
}
