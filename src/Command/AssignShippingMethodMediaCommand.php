<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Command;

use Clueware\InpostShipping\Shipping\ShippingMethodMediaInstaller;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'clueware:inpost:assign-shipping-method-media',
    description: 'Assigns the bundled InPost graphics to the InPost shipping methods.'
)]
final class AssignShippingMethodMediaCommand extends Command
{
    public function __construct(private readonly ShippingMethodMediaInstaller $mediaInstaller)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->mediaInstaller->assign(Context::createCLIContext(), \dirname(__DIR__));

        $output->writeln('<info>InPost shipping-method graphics have been assigned.</info>');

        return Command::SUCCESS;
    }
}
