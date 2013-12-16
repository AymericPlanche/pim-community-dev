<?php

namespace Pim\Bundle\ImportExportBundle\Form\Type;

use Pim\Bundle\ImportExportBundle\Form\Subscriber\JobAliasSubscriber;

use Oro\Bundle\BatchBundle\Connector\ConnectorRegistry;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Oro\Bundle\BatchBundle\Form\Type\JobConfigurationType;
use Pim\Bundle\CatalogBundle\Form\Subscriber\DisableFieldSubscriber;
use Pim\Bundle\ImportExportBundle\Form\Subscriber\RemoveDuplicateJobConfigurationSubscriber;

/**
 * Job instance form type
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class JobInstanceType extends AbstractType
{
    /**
     * @var ConnectorRegistry $connectorRegistry
     */
    protected $connectorRegistry;

    /**
     * @var string $jobType
     */
    protected $jobType;

    /**
     * Constructor
     *
     * @param ConnectorRegistry $connectorRegistry
     */
    public function __construct(ConnectorRegistry $connectorRegistry)
    {
        $this->connectorRegistry = $connectorRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('code', 'text')
            ->addEventSubscriber(new DisableFieldSubscriber('code'))
            ->add('label')
            ->add(
                'connector',
                'choice',
                array(
                    'choices'      => $this->connectorRegistry->getConnectors($this->jobType),
                    'required'     => true,
                    'by_reference' => false,
                    'mapped'       => false
                )
            )
            ->add(
                'alias',
                'choice',
                array(
                    'choices'      => $this->connectorRegistry->getConnector('Akeneo CSV Connector', $this->jobType), //FIXME
                    'required'     => true,
                    'by_reference' => false,
                    'mapped'       => false
                )
            )
            ->addEventSubscriber(new JobAliasSubscriber($this->connectorRegistry))
            ->add(
                'job',
                new JobConfigurationType(),
                array(
                    'required'     => false,
                    'by_reference' => false,
                )
            )
            ->get('job')
            ->addEventSubscriber(new RemoveDuplicateJobConfigurationSubscriber());
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pim_import_export_jobInstance';
    }

    /**
     * Setter for job type
     *
     * @param string $jobType
     *
     * @return \Pim\Bundle\ImportExportBundle\Form\Type\JobInstanceType
     */
    public function setJobType($jobType)
    {
        $this->jobType = $jobType;

        return $this;
    }
}
