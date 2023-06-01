<?php

namespace Kiss\KissBundle\Service;

use App\Entity\Entity;
use App\Service\EavService;
use App\Exception\GatewayException;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class SyncPubService
{
    private EntityManagerInterface $entityManager;
    private GatewayResourceService $resourceService;
    private CallService $callService;
    private SynchronizationService $syncService;
    private MappingService $mappingService;
    private array $data;
    private array $configuration;

    public function __construct(
        EntityManagerInterface $entityManager,
        GatewayResourceService $resourceService,
        CallService $callService,
        SynchronizationService $syncService,
        MappingService $mappingService
    ) {
        $this->entityManager = $entityManager;
        $this->resourceService = $resourceService;
        $this->callService = $callService;
        $this->syncService = $syncService;
        $this->mappingService = $mappingService;
    }

    /**
     * Handles the synchronization of the openpub api.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     */
    public function syncPubHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $source = $this->resourceService->getSource($this->configuration['burenSource'], 'klantinteractie-servicesysteem/kiss-bundle');
        $schema = $this->resourceService->getSchema($this->configuration['pubSchema'], 'klantinteractie-servicesysteem/kiss-bundle');
        $mapping = $this->resourceService->getMapping($this->configuration['pubMapping'], 'klantinteractie-servicesysteem/kiss-bundle');

        $sourceConfig = $source->getConfiguration();

        $response = $this->callService->getAllResults(
            $source,
            '/items',
            $sourceConfig
        );

        $responseItems = [];
        foreach ($response as $result) {
            $synchronization = $this->syncService->findSyncBySource($source, $schema, $result['id']);
            $synchronization->setMapping($mapping);
            $synchronization = $this->syncService->synchronize($synchronization, $result);

            $responseItems[] = $synchronization->getObject()->toArray();
        }

        $this->data['response'] = new Response(json_encode($responseItems), 200);

        return $this->data;
    }
}
