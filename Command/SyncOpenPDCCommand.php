<?php

namespace Kiss\KissBundle\Command;

use Kiss\KissBundle\Service\SyncOpenPDCService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use CommonGateway\CoreBundle\Service\GatewayResourceService;

/**
 * This class handles the command for the sync of OpenPDC.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @package  Kiss\KissBundle
 * @category Command
 */
class SyncOpenPDCCommand extends Command
{

    /**
     * The actual command.
     *
     * @var static
     */
    protected static $defaultName = 'kiss:openpdc:synchronize';

    /**
     * The OpenPDC service.
     *
     * @var SyncOpenPDCService
     */
    private SyncOpenPDCService $syncOpenPDCService;

    /**
     * The Resource service.
     *
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * Class constructor.
     *
     * @param SyncOpenPDCService     $syncOpenPDCService The OpenPDC service
     * @param GatewayResourceService $resourceService    The GatewayResourceService
     */
    public function __construct(SyncOpenPDCService $syncOpenPDCService, GatewayResourceService $resourceService)
    {
        $this->syncOpenPDCService = $syncOpenPDCService;
        $this->resourceService    = $resourceService;
        parent::__construct();

    }//end __construct()


    /**
     * Configures this command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command triggers open pdc synchronization')
            ->setHelp('This command can triggers open pdc synchronization');

    }//end configure()


    /**
     * Executes syncOpenPDCService->syncOpenPDCHandler.
     *
     * @param InputInterface  Handles input from cli
     * @param OutputInterface Handles output from cli
     *
     * @return int 0 for failure, 1 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $this->resourceService->getAction('https://kissdevelopment.commonground.nu/action/buren.SyncOpenPDC.action.json', 'common-gateway/kiss-bundle');

        $style = new SymfonyStyle($input, $output);
        $this->syncOpenPDCService->setStyle($style);
        if ($this->syncOpenPDCService->syncOpenPDCHandler([], $action->getConfiguration()) === null) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;

    }//end execute()


}//end class
