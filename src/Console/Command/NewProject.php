<?php

namespace Nbj\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class NewProject extends Command
{

    /**
     * Holds the name of the project
     *
     * @var string $projectName
     */
    protected $projectName;

    /**
     * Holds the namespace name
     *
     * @var string $namespace
     */
    protected $namespace;

    /**
     * Holds whether or not project will be setup up of testing
     *
     * @var bool $useTesting
     */
    protected $useTesting = false;

    /**
     * Holds whether or not project will use PHP-CS-Fixer
     *
     * @var bool $usePHPCSFixer
     */
    protected $usePHPCSFixer = false;

    /**
     * Holds the structure of the composer.json
     *
     * @var array $composerJsonStructure
     */
    protected $composerJsonStructure = [
        'name'         => 'vendor/project',
        'description'  => 'Project scaffold',
        'type'         => 'project',
        'license'      => 'MIT',
        'require'      => [],
        'require-dev'  => [],
        'autoload'     => [],
        'autoload-dev' => [],
    ];

    /**
     * Configures the current command.
     */
    public function configure()
    {
        parent::configure();

        $this
            ->setName('new')
            ->addArgument('project_name', InputArgument::REQUIRED, 'The name of the project to create')
            ->setDescription('Creates a new project');
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @see setCode()
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(['Creating new project...', '']);
        $questionHelper = $this->getHelper('question');

        $this->projectName = $input->getArgument('project_name');

        $autoloaderQuestion = new ConfirmationQuestion('<question>Setup namespaced autoloading? (Y/n)</question> ');
        if ($questionHelper->ask($input, $output, $autoloaderQuestion)) {
            $this->setupNamespacedAutoloading($input, $output);
        }

        $testsQuestion = new ConfirmationQuestion('<question>Use PHPUnit for testing? (Y/n)</question> ');
        if ($questionHelper->ask($input, $output, $testsQuestion)) {
            $this->setupPHPUnitForTesting();
        }

        $codeStyleQuestion = new ConfirmationQuestion('<question>Use PHP-CS-Fixer for code style fixes? (Y/n)</question> ');
        if ($questionHelper->ask($input, $output, $codeStyleQuestion)) {
            $this->setupPHPCSFixer();
        }

        $this->generateProject($input, $output);

        return 0;
    }

    /**
     * Sets up namespaced autoloading
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function setupNamespacedAutoloading(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getHelper('question');

        $namespaceQuestion = new Question('<question>Enter the name of your namespace: (App)</question> ', 'App');
        $namespace = $questionHelper->ask($input, $output, $namespaceQuestion);

        $this->namespace = sprintf('%s\\', $namespace);
    }

    /**
     * Makes sure project is setup for testing
     */
    private function setupPHPUnitForTesting()
    {
        $this->useTesting = true;
    }

    /**
     * Makes sure project is setup for cs fixes
     */
    private function setupPHPCSFixer()
    {
        $this->usePHPCSFixer = true;
    }

    /**
     * Generates the project folder
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function generateProject(InputInterface $input, OutputInterface $output)
    {
        // Setup project directory
        $projectDir = sprintf("%s/%s", getcwd(), $this->projectName);
        $output->writeln('Creating project folder: ' . $projectDir);

        mkdir($projectDir);
        chdir($projectDir);

        $output->writeln('Creating .gitignore file');
        $fileContent = file_get_contents(STUBS_PATH . '/gitignore');
        file_put_contents('.gitignore', $fileContent);

        // Create namespace stub in composer.json
        if ($this->namespace) {
            $output->writeln('Setting up namespace: ' . $this->namespace . ' in src/');
            $this->composerJsonStructure['autoload']['psr-4'] = [
                $this->namespace => 'src/'
            ];

            mkdir($projectDir . '/src');
        }

        // Create test namespace stub in composer.json
        if ($this->useTesting) {
            $output->writeln('Setting up testing in tests/ using PHPUnit');
            $this->composerJsonStructure['autoload-dev']['psr-4'] = [
                'Tests\\' => 'tests/'
            ];

            $this->composerJsonStructure['require-dev']['phpunit/phpunit'] = '*';

            $output->writeln('Creating phpunit.xml config file');
            $fileContent = file_get_contents(STUBS_PATH . '/phpunit');
            file_put_contents('phpunit.xml', $fileContent);

            $output->writeln('Making folder tests/Unit');
            mkdir($projectDir . '/tests/Unit', 0777, true);

            $output->writeln('Making folder tests/Feature');
            mkdir($projectDir . '/tests/Feature', 0777, true);
        }

        // Create test namespace stub in composer.json
        if ($this->usePHPCSFixer) {
            $output->writeln('Setting up PHP Code fixer');
            $this->composerJsonStructure['require-dev']['friendsofphp/php-cs-fixer'] = '*';

            $output->writeln('Creating .php_cs config file');
            $fileContent = file_get_contents(STUBS_PATH . '/php_cs_fixer');
            file_put_contents('.php-cs-fixer.php', $fileContent);
        }

        foreach ($this->composerJsonStructure as $key => $value) {
            if (is_array($value) && empty($value)) {
                $this->composerJsonStructure[$key] = (object) [];
            }
        }

        $composerJsonContent = json_encode($this->composerJsonStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $output->writeln('Writing composer.json file');
        file_put_contents('composer.json', $composerJsonContent);

        $output->writeln('Run composer install');
        `composer install`;
    }
}
