<?php

namespace Kiss\KissBundle\ActionHandler;

use Kiss\KissBundle\Service\SyncOpenPDCService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;

/**
 * ActionHandler executing SyncOpenPDCService->syncOpenPDCHandler.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @package  Kiss\KissBundle
 * @category ActionHandler
 */
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
            'properties' => [
                'source' => [
                    'type'        => 'string',
                    'description' => 'The reference of the source to fetch from.',
                    'example'     => 'https://buren.nl/source/buren.openpdc.source.jso',
                    'required'    => true,
                ],
                'mapping'   => [
                    'type'        => 'string',
                    'description' => 'The reference of the mapping to map each object with.',
                    'example'     => 'https://buren.nl/mapping/buren.openPDCSourceInBody.mapping.json',
                    'required'    => true,
                ],
                'schema'  => [
                    'type'        => 'string',
                    'description' => 'The reference of the schema of the object we are syncing',
                    'example'     => 'https://kissdevelopment.commonground.nu/kiss.sdgProduct.schema.json',
                    'required'    => true,
                ],
                'endpoint'  => [
                    'type'        => 'string',
                    'description' => 'The endpoint we are fetching from on the source.',
                    'example'     => '/products',
                    'required'    => true,
                ]
            ],
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
