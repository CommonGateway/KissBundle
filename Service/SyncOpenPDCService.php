<?php

namespace Kiss\KissBundle\Service;

use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Symfony\Component\Console\Style\SymfonyStyle;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\HttpFoundation\Response;

class SyncOpenPDCService
{
    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * @var CallService
     */
    private CallService $callService;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

    /**
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SymfonyStyle|null
     */
    private ?SymfonyStyle $style = null;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var array
     */
    private array $configuration;

    public function __construct(
        GatewayResourceService $resourceService,
        CallService $callService,
        SynchronizationService $syncService,
        MappingService $mappingService,
        EntityManagerInterface $entityManager
    ) {
        $this->resourceService = $resourceService;
        $this->callService = $callService;
        $this->syncService = $syncService;
        $this->mappingService = $mappingService;
        $this->entityManager = $entityManager;
    }


    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $style
     *
     * @return self
     *
     * @todo change to monolog
     */
    public function setStyle(SymfonyStyle $style): self
    {
        $this->style = $style;

        return $this;

    }//end setStyle()

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
    public function syncOpenPDCHandler(array $data, array $configuration): array
    {
        $this->style && $this->style->info('SyncOpenPDC triggered');

        $this->data = $data;
        $this->configuration = $configuration;

        $source = $this->resourceService->getSource($this->configuration['source'], 'common-gateway/kiss-bundle');
        $schema = $this->resourceService->getSchema($this->configuration['schema'], 'common-gateway/kiss-bundle');
        $mapping = $this->resourceService->getMapping($this->configuration['mapping'], 'common-gateway/kiss-bundle');
        $endpoint = $this->configuration['endpoint'];

        $sourceConfig = $source->getConfiguration();

        $this->style && $this->style->info('Fetching objects..');
        $response = $this->callService->getAllResults(
            $source,
            $endpoint,
            $sourceConfig
        );

        $responseItems = [];
        foreach ($response as $result) {
            $result = $this->mappingService->mapping($mapping, $result);

            $synchronization = $this->syncService->findSyncBySource($source, $schema, $result['id']);
            // $synchronization->setMapping($mapping);
            $synchronization = $this->syncService->synchronize($synchronization, $result);
            $this->entityManager->persist($synchronization);

            $responseItems[] = $synchronization->getObject()->toArray();
        }
        $this->entityManager->flush();

        $this->style && $this->style->success('Synchronized ' . count($responseItems) . ' objects');

        $this->data['response'] = new Response(json_encode($responseItems), 200);

        return $this->data;
    }
}
