<?php

declare(strict_types=1);

namespace PetitPress\GpsMessengerBundle\Transport;

use Google\Cloud\PubSub\PubSubClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * @author Ronald Marfoldi <ronald.marfoldi@petitpress.sk>
 */
final class GpsTransport implements TransportInterface, SetupableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface
{
    private PubSubClient $pubSubClient;
    private GpsConfigurationInterface $gpsConfiguration;
    private SerializerInterface $serializer;
    private GpsReceiver $receiver;
    private GpsSender $sender;

    public function __construct(
        PubSubClient $pubSubClient,
        GpsConfigurationInterface $gpsConfiguration,
        SerializerInterface $serializer
    ) {
        $this->pubSubClient = $pubSubClient;
        $this->gpsConfiguration = $gpsConfiguration;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        return $this->getReceiver()->get();
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageCount(): int
    {
        return $this->getReceiver()->getMessageCount();
    }

    /**
     * {@inheritdoc}
     */
    public function all(?int $limit = null): iterable
    {
        return $this->getReceiver()->all($limit);
    }

    /**
     * {@inheritdoc}
     */
    public function find(mixed $id): ?Envelope
    {
        return $this->getReceiver()->find($id);
    }

    public function getReceiver(): GpsReceiver
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (isset($this->receiver)) {
            return $this->receiver;
        }

        $this->receiver = new GpsReceiver($this->pubSubClient, $this->gpsConfiguration, $this->serializer);

        return $this->receiver;
    }

    public function getSender(): GpsSender
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (isset($this->sender)) {
            return $this->sender;
        }

        $this->sender = new GpsSender($this->pubSubClient, $this->gpsConfiguration, $this->serializer);

        return $this->sender;
    }

    public function setup(): void
    {
        $topic = $this->pubSubClient->topic($this->gpsConfiguration->getTopicName());

        if (true === $this->gpsConfiguration->isTopicCreationEnabled() && false === $topic->exists()) {
            $topic = $this->pubSubClient->createTopic(
                $this->gpsConfiguration->getTopicName(),
                $this->gpsConfiguration->getTopicOptions()
            );
        }

        $subscription = $topic->subscription($this->gpsConfiguration->getSubscriptionName());

        if (true === $this->gpsConfiguration->isSubscriptionCreationEnabled() && false === $subscription->exists()) {
            $topic->subscribe(
                $this->gpsConfiguration->getSubscriptionName(),
                $this->gpsConfiguration->getSubscriptionOptions()
            );
        }
    }
}
