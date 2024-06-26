<?php

namespace Kiss\KissBundle\ActionHandler;

use Kiss\KissBundle\Service\SyncElasticService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;

class SyncElasticHandler implements ActionHandlerInterface
{
    private SyncElasticService $service;

    public function __construct(SyncElasticService $service)
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
            '$id'        => 'https://kissdevelopment.commonground.nu/actionHandler/kiss.SyncPubAction.actionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'SyncElasticHandler',
            'description'=> 'Handles the sync for pub.',
            'required'   => [],
            'properties' => [
                "burenSource" => [
                    'type'        => 'string',
                    'description' => 'The id of the source to load the data from.',
                    'example'     => 'https://buren.nl/source/buren.sdgplus.source.json',
                    'nullable'    => false,
                    '$ref'        => 'https://buren.nl/source/buren.sdgplus.source.json'
                ],
                "elasticSource" => [
                    'type'        => 'string',
                    'description' => 'The id of the source to push the data to.',
                    'example'     => 'https://kissdevelopment.commonground.nu/source/kiss.enterpriseSearchPrivate.source.json',
                    'nullable'    => false,
                    '$ref'        => 'https://kissdevelopment.commonground.nu/source/kiss.enterpriseSearchPrivate.source.json'
                ],
                "sdgMapping" => [
                    'type'        => 'string',
                    'description' => 'The id of the source to load the data from.',
                    'example'     => 'https://buren.nl/mapping/buren.sdgProduct.mapping.json',
                    'nullable'    => false,
                    '$ref'        => 'https://buren.nl/mapping/buren.sdgProduct.mapping.json'
                ],
            ],
        ];
    }

    /**
     * This function runs the SyncElastic service plugin.
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
        return $this->service->syncElasticHandler($data, $configuration);
    }
}
