<?php

namespace Tuchsoft\IssueReporter\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tuchsoft\IssueReporter\Factory;
use Tuchsoft\IssueReporter\Format\Base\NativeFormatInterface;
use Tuchsoft\IssueReporter\Format\Base\ParsableFormatInterface;

class ListFormat  extends ListCommand {

    protected static $defaultName = 'list-format';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Runs Moodle plugin checks and generates a report.')
            ->setHelp("The <info>%command.name%</info> command lists all available output formats:\n\n<info>%command.full_name%</info>");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->getFormatter()->setStyle('cmd', new OutputFormatterStyle('bright-blue'));
        $io->getFormatter()->setStyle('txt', new OutputFormatterStyle('bright-white'));
        $io->title('Available output format');
        foreach (Factory::getRegistered(Factory::FORMAT) as $format) {
            $parsable = is_subclass_of($format, ParsableFormatInterface::class );
            $io->text("<txt>Name:</txt> <cmd>{$format::getName()}</cmd>");
            $io->text("<txt>Description:</txt> {$format::getDesc()}");
            $io->text("<txt>Format:</txt> {$format::getFormat()}");
            $io->text('<txt>Parsable:</txt> '.($parsable ? 'yes' : 'no'));
            $io->text("<txt>Options:</txt> ");
            $helper = new DescriptorHelper();
            /** @var InputOption $option */
            foreach (array_unique($format::getOptionsDefinition(), SORT_REGULAR) as $option) {
                $helper->describe($io, $option, ['format' => 'txt']);
                if ($option->isNegatable()) {
                    $default =
                        ($option->getDefault() ? '(true) ' : '(false) ') .
                        ($option->getDefault() ? '--' : '--no-') .
                        $option->getName();
                    $io->write(" <comment>[default: $default]]</comment>");
                }
                $io->newLine();
            }
            if (is_a($format, ParsableFormatInterface::class, true)) {
                $io->text("<txt>Features:</txt>");
                $this->list($io,$format::supports());
                if (($extra = $format::supportsExtra())) {
                    $io->text("<txt>Extra features (not natively supported):</txt>");
                    $this->list($io,$extra);
                }
            } else if (is_a($format, NativeFormatInterface::class, true)) {
                $io->text("<txt>Features:</txt>");
                $this->list($io,["Native format supports all features but are not parsable"]);
            }

            $io->newLine();
            $io->text(str_repeat('-', 20));
            $io->newLine();
        }
        return Command::SUCCESS;
    }


    private function list($io, $els) {
        $io->write(array_map(fn ($e) => '      - '.ucfirst(str_replace('-',' ', $e))."\n", $els));
    }
}