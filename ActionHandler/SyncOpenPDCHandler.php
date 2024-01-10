<?php

namespace Kiss\KissBundle\ActionHandler;

use Kiss\KissBundle\Service\SyncOpenPDCService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;

class SyncOpenPDCHandler implements ActionHandlerInterface
{
    private SyncOpenPDCService $service;

    public function __construct(SyncOpenPDCService $service)
    {
        $this->service = $service;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://kissdevelopment.commonground.nu/actionHandler/kiss.SyncOpenPDCAction.actionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'SyncOpenPDCHandler',
            'description'=> 'Handles the sync for the Open PDC.',
            'required'   => [],
            'properties' => [],
        ];
    }

    /**
     * This function runs the SyncOpenPDC service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->service->syncOpenPDCHandler($data, $configuration);
    }
}
