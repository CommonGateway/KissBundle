<?php

namespace Kiss\KissBundle\Service;

use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\HydrationService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
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
     * @var HydrationService
     */
    private HydrationService $hydrationService;

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
     * @var OutputInterface|null
     */
    private ?OutputInterface $output = null;

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
     * @param HydrationService $hydrationService      The hydration service.
     * @param MappingService $mappingService          The Mapping Service.
     * @param EntityManagerInterface $entityManager   The entity manager.
     * @param LoggerInterface $pluginLogger           The plugin version of the logger interface
     */
    public function __construct(
        GatewayResourceService $resourceService,
        CallService $callService,
        HydrationService $hydrationService,
        MappingService $mappingService,
        EntityManagerInterface $entityManager,
        LoggerInterface $pluginLogger
    ) {
        $this->resourceService = $resourceService;
        $this->callService = $callService;
        $this->hydrationService = $hydrationService;
        $this->mappingService = $mappingService;
        $this->entityManager = $entityManager;
        $this->logger = $pluginLogger;
    }
    
    
    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $style
     * @param OutputInterface|null $output
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $style, ?OutputInterface $output = null): self
    {
        $this->style = $style;
        $this->output = $output;

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

        $this->style && $this->style->info("syncOpenPDCHandler {$this->configuration['source']}, fetching objects...");
        $this->logger->info("syncOpenPDCHandler {$this->configuration['source']}, fetching objects...", ['plugin' => 'common-gateway/kiss-bundle']);
        $response = $this->callService->getAllResults(
            $source,
            $endpoint,
            $sourceConfig
        );
        
        $this->style && $this->style->info('syncOpenPDCHandler, syncing objects...');
        $this->logger->info('syncOpenPDCHandler, syncing objects...', ['plugin' => 'common-gateway/kiss-bundle']);
        
        $responseCount = count($response);
        $this->output && count($response) !== 0 && $progressBar = new ProgressBar($this->output, $responseCount);
        $this->output === null && $this->style->writeln('0 / '.$responseCount);
        
        $idsSynced     = [];
        $responseItems = [];
        foreach ($response as $result) {
            $result = $this->mappingService->mapping($mapping, $result);
            
            $object = $this->hydrationService->searchAndReplaceSynchronizations($result, $source, $schema, true, true);

            if ($object instanceof ObjectEntity === true) {
                $responseItems[] = $object->toArray();
            } else {
                $responseItems[] = $object;
            }
            
            // Get all synced sourceIds.
            if (empty($object->getSynchronizations()) === false && $object->getSynchronizations()[0]->getSourceId() !== null) {
                $idsSynced[] = $object->getSynchronizations()[0]->getSourceId();
            }
            
            $this->output && isset($progressBar) && $progressBar->advance();
            if ($this->output === null && count($responseItems) !== $responseCount && count($responseItems) % 25 === 0) {
                $this->style->writeln(count($responseItems).' / '.$responseCount);
            }
        }
        $this->output && isset($progressBar) && $progressBar->finish();
        $this->output === null && $this->style->writeln(count($responseItems).' / '.$responseCount);
        
        $this->style && $this->style->newLine();
        $this->style && $this->style->info('syncOpenPDCHandler, flushing objects...');
        $this->logger->info('syncOpenPDCHandler, flushing objects...', ['plugin' => 'common-gateway/kiss-bundle']);
        
        $this->entityManager->flush();
        
        $this->style && $this->style->info('syncOpenPDCHandler, checking if we need to delete objects that no longer exist in the source...');
        $this->logger->info('syncOpenPDCHandler, checking if we need to delete objects that no longer exist in the source...', ['plugin' => 'common-gateway/kiss-bundle']);
        $deletedObjectsCount = $this->deleteNonExistingObjects($idsSynced, $source, $this->configuration['schema']);

        $this->style && $this->style->success('syncOpenPDCHandler synchronized ' . count($responseItems) . ' objects and deleted ' . $deletedObjectsCount . ' objects');
        $this->logger->info('syncOpenPDCHandler synchronized ' . count($responseItems) . ' objects and deleted ' . $deletedObjectsCount . ' objects', ['plugin' => 'common-gateway/kiss-bundle']);

        $this->data['response'] = new Response(json_encode($responseItems), 200);

        return $this->data;
    }
    
    
    /**
     * Checks if existing objects still exist in the source, if not deletes them.
     *
     * @param array       $idsSynced ID's from objects we just synced from the source.
     * @param Source      $source    These objects belong to.
     * @param string      $schemaRef These objects belong to.
     *
     * @return int Count of deleted objects.
     */
    public function deleteNonExistingObjects(array $idsSynced, Source $source, string $schemaRef): int
    {
        // Get all existing sourceIds.
        $source            = $this->entityManager->find('App:Gateway', $source->getId()->toString());
        $existingSourceIds = [];
        $existingObjects   = [];
        foreach ($source->getSynchronizations() as $synchronization) {
            if ($synchronization->getEntity()->getReference() === $schemaRef && $synchronization->getSourceId() !== null) {
                $existingSourceIds[] = $synchronization->getSourceId();
                $existingObjects[]   = $synchronization->getObject();
            }
        }
        
        // Check if existing sourceIds are in the array of new synced sourceIds.
        $objectIdsToDelete = array_diff($existingSourceIds, $idsSynced);
        
        // If not it means the object does not exist in the source anymore and should be deleted here.
        $deletedObjectsCount = 0;
        foreach ($objectIdsToDelete as $key => $id) {
            $this->logger->info("Object $id does not exist at the source, deleting.", ['plugin' => 'common-gateway/woo-bundle']);
            $this->entityManager->remove($existingObjects[$key]);
            $deletedObjectsCount++;
        }
        
        $this->entityManager->flush();
        
        return $deletedObjectsCount;
        
    }//end deleteNonExistingObjects()
}
