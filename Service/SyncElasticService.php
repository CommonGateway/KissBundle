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

class SyncElasticService
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
     * Handles the synchronization of the SDG+ api to ElasticSearch.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws CacheException|InvalidArgumentException
     *
     * @return array
     */
    public function syncElasticHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $source        = $this->resourceService->getSource($this->configuration['burenSource'], 'klantinteractie-servicesysteem/kiss-bundle');
        $elasticSource = $this->resourceService->getSource($this->configuration['elasticSource'], 'klantinteractie-servicesysteem/kiss-bundle');
        $mapping       = $this->resourceService->getMapping($this->configuration['sdgMapping'], 'klantinteractie-servicesysteem/kiss-bundle');

        $sourceConfig = $source->getConfiguration();

        $response = $this->callService->getAllResults(
            $source,
            '',
            $sourceConfig
        );

        $responseItems = [];
        foreach ($response as $result) {
            $elasticData = $this->mappingService->mapping($mapping, ['object' => $result]);

            $elasticData['object'] = str_replace('&quot;', '"', $elasticData['object']);

            $response = $this->callService->call($elasticSource, '/api/as/v1/engines/kiss-engine/documents', 'POST', ['body' => \Safe\json_encode($elasticData)]);

            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
                $this->data['response'] = new Response('Could not synchronise to elasticSearch, '. $response->getBody()->getContents(), 422);
            }
        }

        $this->data['response'] = new Response(json_encode($responseItems), 200);

        return $this->data;
    }
}
