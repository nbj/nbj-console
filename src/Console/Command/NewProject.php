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
     * @return void null or 0 if everything went fine, or an error code
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

        mkdir($projectDir);
        chdir($projectDir);

        // Create namespace stub in composer.json
        if ($this->namespace) {
            $this->composerJsonStructure['autoload']['psr-4'] = [
                $this->namespace => 'src/'
            ];
        }

        // Create test namespace stub in composer.json
        if ($this->useTesting) {
            $this->composerJsonStructure['autoload-dev']['psr-4'] = [
                'Tests\\' => 'tests/'
            ];

            $this->composerJsonStructure['require-dev']['phpunit/phpunit'] = '*';

            $fileContent = file_get_contents(STUBS_PATH . '/phpunit');
            file_put_contents('phpunit.xml', $fileContent, FILE_BINARY);
        }

        // Create test namespace stub in composer.json
        if ($this->usePHPCSFixer) {
            $this->composerJsonStructure['require-dev']['friendsofphp/php-cs-fixer'] = '*';

            $fileContent = file_get_contents(STUBS_PATH . '/php_cs_fixer');
            file_put_contents('.php_cs', $fileContent, FILE_BINARY);
        }

        foreach ($this->composerJsonStructure as $key => $value) {
            if (is_array($value) && empty($value)) {
                $this->composerJsonStructure[$key] = (object) [];
            }
        }

        $composerJsonContent = json_encode($this->composerJsonStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents('composer.json', $composerJsonContent, FILE_BINARY);
    }
}
