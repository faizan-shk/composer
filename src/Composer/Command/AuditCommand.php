<?php declare(strict_types=1);

namespace Composer\Command;

use Composer\Composer;
use Composer\Repository\RepositorySet;
use Composer\Repository\RepositoryUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepository;
use Composer\Advisory\Auditor;
use Composer\Console\Input\InputOption;

class AuditCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('audit')
            ->setDescription('Checks for security vulnerability advisories for installed packages.')
            ->setDefinition(array(
                new InputOption('no-dev', null, InputOption::VALUE_NONE, 'Disables auditing of require-dev packages.'),
                new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format. Must be "table", "plain", or "summary".', Auditor::FORMAT_TABLE, Auditor::FORMATS),
                new InputOption('locked', null, InputOption::VALUE_NONE, 'Audit based on the lock file instead of the installed packages.'),
            ))
            ->setHelp(
                <<<EOT
The <info>audit</info> command checks for security vulnerability advisories for installed packages.

If you do not want to include dev dependencies in the audit you can omit them with --no-dev

Read more at https://getcomposer.org/doc/03-cli.md#audit
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $composer = $this->requireComposer();
        $packages = $this->getPackages($composer, $input);

        if (count($packages) === 0) {
            $this->getIO()->writeError('No packages - skipping audit.');
            return 0;
        }

        $auditor = new Auditor();
        $repoSet = new RepositorySet();
        foreach ($composer->getRepositoryManager()->getRepositories() as $repo) {
            $repoSet->addRepository($repo);
        }

        return min(255, $auditor->audit($this->getIO(), $repoSet, $packages, $this->getAuditFormat($input, 'format'), false));
    }

    /**
     * @param InputInterface $input
     * @return PackageInterface[]
     */
    private function getPackages(Composer $composer, InputInterface $input): array
    {
        if ($input->getOption('locked')) {
            if (!$composer->getLocker()->isLocked()) {
                throw new \UnexpectedValueException('Valid composer.json and composer.lock files are required to run this command with --locked');
            }
            $locker = $composer->getLocker();
            return $locker->getLockedRepository(!$input->getOption('no-dev'))->getPackages();
        }

        $rootPkg = $composer->getPackage();
        $installedRepo = new InstalledRepository(array($composer->getRepositoryManager()->getLocalRepository()));

        if ($input->getOption('no-dev')) {
            return RepositoryUtils::filterRequiredPackages($installedRepo->getPackages(), $rootPkg);
        }

        return $installedRepo->getPackages();
    }
}
