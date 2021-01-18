<?php

namespace Genhoi\SymfonyNotifier;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

final class SmscTransportFactory extends AbstractTransportFactory
{
    /**
     * @return TransportInterface
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('smsc' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'smsc', $this->getSupportedSchemes());
        }

        $login = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        $sender = $dsn->getOption('from');
        if ($sender === null) {
            $sender = $dsn->getOption('sender');
        }

        $transport = new SmscTransport($login, $password, $this->client, $this->dispatcher);
        $transport
            ->setHost($host)
            ->setPort($port)
            ->setSender($sender);

        return $transport;
    }

    protected function getSupportedSchemes(): array
    {
        return ['smsc'];
    }
}
