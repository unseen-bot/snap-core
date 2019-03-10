<?php

namespace Snap\Commands;

use Snap\Commands\Concerns\Needs_Wordpress;
use Snap\Commands\Concerns\Uses_Filesystem;
use Snap\Core\Loader;
use Snap\Core\Snap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Primes and creates the cache.
 */
class Cache extends Command
{
    use Needs_Wordpress, Uses_Filesystem;

    /**
     * Setup the command signature and help text.
     */
    protected function configure()
    {
        $this->setName('cache:generate')
            ->setDescription('Caches config and templates for a production environment.')
            ->setHelp('Caches config and templates for a production environment.');

        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force cache, even in a production environment.'
        );
    }

    /**
     * Run the command.
     *
     * @param  InputInterface  $input  Command input.
     * @param  OutputInterface $output Command output.
     * @throws \Hodl\Exceptions\ContainerException If something is wrong with the container.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init_wordpress();
        $this->setup_filesystem();

        if ($input->getOption('force') === false && (!\defined('WP_DEBUG') || WP_DEBUG === false)) {
            if ($this->confirm_choice($input, $output) === false) {
                return;
            }
        }

        // Setup Snap.
        Snap::create_container();
        Snap::init_config();

        $loader = new Loader();
        $loader->load_theme();

        $config = Snap::get_container()->get('config');

        $root = \get_template_directory();

        if (\is_child_theme()) {
            $root = \get_stylesheet_directory();
        }

        $cache_path = \trailingslashit($root) . \trailingslashit($config->get('theme.cache_directory')) . 'config/';

        // Make the cache directory if it doesn't exist.
        if (!\is_dir($cache_path)) {
            \wp_mkdir_p($cache_path);
        }

        $config_created = $this->file->put_contents(
            $cache_path . \sha1(NONCE_SALT . 'theme'),
            \serialize($config->get_primed_cache())
        );

        $autoload_created = $this->file->put_contents(
            $cache_path . \sha1(NONCE_SALT . 'classmap'),
            \serialize($loader->get_theme_includes())
        );

        if ($config_created && $autoload_created) {
            $output->writeln('<info>Snap cache was set up successfully.</info>');
            return;
        }

        $output->writeln('<error>Snap cache could not be set up successfully.</error>');
    }

    /**
     * Confirm choice of user to cache.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return bool
     */
    private function confirm_choice(InputInterface $input, OutputInterface $output): bool
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            '<fg=yellow>The site seems to be in a production environment, are you sure you want to continue?</>',
            false
        );

        return $helper->ask($input, $output, $question);
    }
}
