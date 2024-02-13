<?php

namespace Kiss\KissBundle\Service;

use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Symfony\Component\Console\Style\SymfonyStyle;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service responsible for synchronizing OpenPDC objects.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @package  Kiss\KissBundle
 * @category Service
 */
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
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

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
    
    /**
     * @param GatewayResourceService $resourceService The Resource Service.
     * @param CallService $callService                The Call Service.
     * @param SynchronizationService $syncService     The synchronization service.
     * @param MappingService $mappingService          The Mapping Service.
     * @param EntityManagerInterface $entityManager   The entity manager.
     * @param LoggerInterface $pluginLogger           The plugin version of the logger interface
     */
    public function __construct(
        GatewayResourceService $resourceService,
        CallService $callService,
        SynchronizationService $syncService,
        MappingService $mappingService,
        EntityManagerInterface $entityManager,
        LoggerInterface $pluginLogger
    ) {
        $this->resourceService = $resourceService;
        $this->callService = $callService;
        $this->syncService = $syncService;
        $this->mappingService = $mappingService;
        $this->entityManager = $entityManager;
        $this->logger = $pluginLogger;
    }


    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $style
     *
     * @return self
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
        $this->logger->info('SyncOpenPDC triggered', ['plugin' => 'common-gateway/kiss-bundle']);

        $this->data = $data;
        $this->configuration = $configuration;

        $source = $this->resourceService->getSource($this->configuration['source'], 'common-gateway/kiss-bundle');
        $schema = $this->resourceService->getSchema($this->configuration['schema'], 'common-gateway/kiss-bundle');
        $mapping = $this->resourceService->getMapping($this->configuration['mapping'], 'common-gateway/kiss-bundle');
        $endpoint = $this->configuration['endpoint'];

        if ($source === null || $schema === null || $mapping === null || $endpoint === null) {
            $this->style && $this->style->error('Source, schema, mapping or endpoint not set in action config or not findable. Stopping syncOpenPDCHandler action.');
            $this->logger->error('Source, schema, mapping or endpoint not set in action config or not findable. Stopping syncOpenPDCHandler action.', ['plugin' => 'common-gateway/kiss-bundle']);

            return $this->data;
        }

        $sourceConfig = $source->getConfiguration();

        $this->style && $this->style->info('syncOpenPDCHandler, fetching objects...');
        $this->logger->info('syncOpenPDCHandler, fetching objects...', ['plugin' => 'common-gateway/kiss-bundle']);
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

        $this->style && $this->style->success('syncOpenPDCHandler synchronized ' . count($responseItems) . ' objects');
        $this->logger->info('syncOpenPDCHandler synchronized ' . count($responseItems) . ' objects', ['plugin' => 'common-gateway/kiss-bundle']);

        $this->data['response'] = new Response(json_encode($responseItems), 200);

        return $this->data;
    }
}
